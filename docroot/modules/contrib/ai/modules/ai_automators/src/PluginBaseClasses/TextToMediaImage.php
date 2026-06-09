<?php

namespace Drupal\ai_automators\PluginBaseClasses;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Guardrail\AiGuardrailHelper;
use Drupal\ai\OperationType\TextToImage\TextToImageInput;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Drupal\ai_automators\Traits\FileHelperTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This is a base class that can be used for image generators.
 */
class TextToMediaImage extends RuleBase implements ContainerFactoryPluginInterface {

  use FileHelperTrait;

  /**
   * {@inheritDoc}
   */
  protected string $llmType = 'text_to_image';

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
   */
  final public function __construct(
    AiProviderPluginManager $pluginManager,
    AiProviderFormHelper $formHelper,
    PromptJsonDecoderInterface $promptJsonDecoder,
    AiGuardrailHelper $aiGuardrailHelper,
    EntityTypeBundleInfo $entityTypeBundleInfo,
    EntityTypeManagerInterface $entityTypeManager,
    EntityFieldManagerInterface $fieldManager,
  ) {
    parent::__construct($pluginManager, $formHelper, $promptJsonDecoder, $aiGuardrailHelper);
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->entityTypeManager = $entityTypeManager;
    $this->fieldManager = $fieldManager;
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
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This can generate images from text.";
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
  public function extraFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []) {
    $form = parent::extraFormFields($entity, $fieldDefinition, $formState, $defaultValues);

    $options = [];
    $types = $this->entityTypeBundleInfo->getBundleInfo('media');
    foreach ($types as $key => $type) {
      $options[$key] = $type['label'];
    }

    $form['automator_llm_media_type'] = [
      '#type' => 'select',
      '#title' => 'Media Type',
      '#description' => $this->t('The media type to use for the generated image. Avoid having required fields default to empty values, e.g. use automators to set them based upon the generated image.'),
      '#options' => $options,
      '#default_value' => $defaultValues['automator_llm_media_type'] ?? '',
      '#empty_option' => $this->t('- Please select -'),
      '#required' => TRUE,
      '#weight' => 4,
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
    // Generate the real prompt if needed.
    $prompts = parent::generate($entity, $fieldDefinition, $automatorConfig);

    // Generate the images.
    $images = [];
    $instance = $this->prepareLlmInstance('text_to_image', $automatorConfig);

    foreach ($prompts as $prompt) {
      // The image binary.
      $input = new TextToImageInput($prompt);
      $response = $instance->textToImage($input, $automatorConfig['ai_model'])->getNormalized();
      if (!empty($response)) {
        foreach ($response as $image) {
          $images[] = [
            'filename' => $this->getFileName($automatorConfig),
            'binary' => $image->getAsBinary(),
            'prompt' => $prompt,
          ];
        }
      }
    }
    return $images;
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
    $mediaType = $automatorConfig['llm_media_type'];
    $mediaTypeInterface = $this->entityTypeManager->getStorage('media_type')->load($mediaType);
    /** @var \Drupal\media\Entity\Media */
    $media = $mediaStorage->create([
      'name' => 'tmp',
      'bundle' => $mediaType,
    ]);
    $sourceField = $media->getSource()->getSourceFieldDefinition($mediaTypeInterface);
    $fileField = $sourceField->getName();
    $mediaFields = $this->fieldManager->getFieldDefinitions('media', $mediaType);

    foreach ($values as $value) {
      $fileHelper = $this->getFileHelper();
      $path = $fileHelper->createFilePathFromFieldConfig($value['filename'], $mediaFields[$fileField], $entity);
      $imageData = $fileHelper->generateImageMetaDataFromBinary($value['binary'], $path);
      $media = $mediaStorage->create([
        'name' => mb_substr($value['prompt'], 0, 250),
        'bundle' => $mediaType,
        $fileField => $imageData,
      ]);
      $media->save();

      $medias[] = $media->id();
    }
    // Then set the value.
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
    return 'ai_generated.jpg';
  }

}
