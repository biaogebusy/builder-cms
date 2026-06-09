<?php

namespace Drupal\ai_automators\PluginBaseClasses;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Guardrail\AiGuardrailHelper;
use Drupal\ai\OperationType\TextToSpeech\TextToSpeechInput;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Drupal\ai_automators\AiPromptHelper;
use Drupal\ai_automators\Traits\FileHelperTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This is a base class that can be used for image generators.
 */
class TextToMediaSpeech extends RuleBase implements ContainerFactoryPluginInterface {

  use FileHelperTrait;

  /**
   * {@inheritDoc}
   */
  protected string $llmType = 'text_to_speech';

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected EntityTypeBundleInfo $entityTypeBundleInfo;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $fieldManager;

  /**
   * The prompt json decoder.
   *
   * @var \Drupal\ai\service\PromptJsonDecoder\PromptJsonDecoderInterface
   */
  protected PromptJsonDecoderInterface $promptJsonDecoder;

  /**
   * The Ai prompt helper.
   *
   * @var \Drupal\ai_automators\AiPromptHelper
   */
  protected $aiPromptHelper;

  /**
   * Module Handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new AiClientBase abstract class.
   *
   * @param \Drupal\ai\AiProviderPluginManager $pluginManager
   *   The plugin manager.
   * @param \Drupal\ai\Service\AiProviderFormHelper $formHelper
   *   The form helper.
   * @param \Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface $promptJsonDecoder
   *   The prompt json decoder.
   * @param \Drupal\ai\Guardrail\AiGuardrailHelper $aiGuardrailHelper
   *   The AI guardrail helper.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfo $entityTypeBundleInfo
   *   The entity type bundle info.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $fieldManager
   *   The field manager.
   * @param \Drupal\ai_automators\AiPromptHelper $aiPromptHelper
   *   The Ai prompt helper.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  final public function __construct(
    AiProviderPluginManager $pluginManager,
    AiProviderFormHelper $formHelper,
    PromptJsonDecoderInterface $promptJsonDecoder,
    AiGuardrailHelper $aiGuardrailHelper,
    EntityTypeBundleInfo $entityTypeBundleInfo,
    EntityTypeManagerInterface $entityTypeManager,
    EntityFieldManagerInterface $fieldManager,
    AiPromptHelper $aiPromptHelper,
    ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($pluginManager, $formHelper, $promptJsonDecoder, $aiGuardrailHelper);
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->entityTypeManager = $entityTypeManager;
    $this->fieldManager = $fieldManager;
    $this->aiPromptHelper = $aiPromptHelper;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('ai.provider'),
      $container->get('ai.form_helper'),
      $container->get('ai.prompt_json_decode'),
      $container->get('ai.guardrail_helper'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('ai_automator.prompt_helper'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This can generate audio from text.";
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "{{ context }}, 50mm portrait photography, hard rim lighting photography-beta";
  }

  /**
   * {@inheritDoc}
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []) {
    $form = parent::extraAdvancedFormFields($entity, $fieldDefinition, $formState, $defaultValues);
    $options = [];
    $types = $this->entityTypeBundleInfo->getBundleInfo('media');

    foreach ($types as $key => $type) {
      $options[$key] = $type['label'];
    }

    $form['automator_llm_media_type'] = [
      '#type' => 'select',
      '#title' => 'Media Type',
      '#description' => $this->t('Media Type to create'),
      '#options' => $options,
      '#default_value' => $defaultValues['automator_llm_media_type'] ?? '',
      '#empty_option' => $this->t('- Please select -'),
    ];
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateConfigValues($form, FormStateInterface $formState) {
    parent::validateConfigValues($form, $formState);
    $values = $formState->getValues();
    if (empty($values['automator_llm_media_type'])) {
      $formState->setErrorByName('automator_llm_media_type', 'Media Type is required.');
    }
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    $fieldDefinitions = $this->fieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());

    // Validate base_field if present.
    if (isset($automatorConfig['base_field']) && !isset($fieldDefinitions[$automatorConfig['base_field']])) {
      return [];
    }

    // Generate the real prompt if needed.
    $prompts = [];
    if (!empty($automatorConfig['mode']) && $automatorConfig['mode'] == 'token' && $this->moduleHandler->moduleExists('token')) {
      $prompts[] = $this->aiPromptHelper->renderTokenPrompt($automatorConfig['token'], $entity);
    }
    elseif ($this->needsPrompt()) {
      // Run rule.
      foreach ($entity->get($automatorConfig['base_field'])->getValue() as $i => $item) {
        // Get tokens.
        $tokens = $this->generateTokens($entity, $fieldDefinition, $automatorConfig, $i);
        $prompts[] = $this->aiPromptHelper->renderPrompt($automatorConfig['prompt'], $tokens, $i);
      }
    }

    // Generate the audio files.
    $audios = [];
    $instance = $this->prepareLlmInstance('text_to_speech', $automatorConfig);

    foreach ($prompts as $prompt) {
      // The audio binary.
      $input = new TextToSpeechInput($prompt);
      $response = $instance->textToSpeech($input, $automatorConfig['ai_model'])->getNormalized();
      if (!empty($response)) {
        foreach ($response as $audio) {
          $audios[] = [
            'filename' => $this->getFileName($automatorConfig),
            'binary' => $audio->getAsBinary(),
            'prompt' => $prompt,
          ];
        }
      }
    }
    return $audios;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    if (!isset($value['filename'])) {
      return FALSE;
    }
    // Detect if binary.
    return preg_match('~[^\x20-\x7E\t\r\n]~', $value['binary']) > 0;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    $medias = [];

    // Prepare storage.
    $mediaStorage = $this->entityTypeManager->getStorage('media');
    $mediaType = $automatorConfig['llm_media_type'] ?? '';

    if (empty($mediaType)) {
      return FALSE;
    }

    // Load the media type.
    $mediaTypeInterface = $this->entityTypeManager->getStorage('media_type')->load($mediaType);
    if (!$mediaTypeInterface) {
      return FALSE;
    }

    // Get the source field definition.
    $sourceFieldDefinition = $mediaTypeInterface->getSource()->getSourceFieldDefinition($mediaTypeInterface);
    if (!$sourceFieldDefinition) {
      return FALSE;
    }

    $fileField = $sourceFieldDefinition->getName();
    $mediaFields = $this->fieldManager->getFieldDefinitions('media', $mediaType);

    if (!isset($mediaFields[$fileField])) {
      return FALSE;
    }

    foreach ($values as $value) {
      $fileHelper = $this->getFileHelper();
      $path = $fileHelper->createFilePathFromFieldConfig($value['filename'], $mediaFields[$fileField], $entity);
      $file = $fileHelper->generateFileFromBinary($value['binary'], $path);

      if (!$file) {
        continue;
      }

      // Create the media entity.
      $media = $mediaStorage->create([
        'bundle' => $mediaType,
        'name' => mb_substr($value['prompt'], 0, 250),
        $fileField => [
          'target_id' => $file->id(),
        ],
      ]);
      $media->save();
      $medias[] = $media->id();
    }

    if (empty($medias)) {
      return FALSE;
    }

    // Set the value on the entity.
    $entity->set($fieldDefinition->getName(), $medias);
    return TRUE;
  }

  /**
   * Gets the filename. Override this.
   *
   * @param array $args
   *   If arguments are needed to create the filename.
   *
   * @return string
   *   The filename.
   */
  public function getFileName(array $args = []) {
    return 'ai_generated.mp3';
  }

}
