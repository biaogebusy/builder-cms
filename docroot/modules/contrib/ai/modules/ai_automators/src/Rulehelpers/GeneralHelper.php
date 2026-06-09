<?php

namespace Drupal\ai_automators\Rulehelpers;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Token;
use Drupal\ai\Service\PromptCodeBlockExtractor\PromptCodeBlockExtractorInterface;
use Drupal\ai_automators\FormAlter\AiAutomatorFieldConfig;
use Drupal\file\FileInterface;
use Drupal\token\TreeBuilder;

/**
 * Helper functions for most rules.
 */
class GeneralHelper {

  use StringTranslationTrait;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The AI Automator field config.
   *
   * @var \Drupal\ai_automators\FormAlter\AiAutomatorFieldConfig
   */
  protected $aiAutomatorFieldConfig;

  /**
   * The token system.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The token tree builder.
   *
   * @var Drupal\token\TreeBuilder
   */
  protected $tokenTreeBuilder;

  /**
   * Prompt code block extractor.
   *
   * @var \Drupal\ai\PromptCodeBlockExtractor\PromptCodeBlockExtractor
   */
  protected $promptCodeBlockExtractor;

  /**
   * Constructor for the class.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\ai_automators\FormAlter\AiAutomatorFieldConfig $aiAutomatorFieldConfig
   *   The AI Automator field config.
   * @param \Drupal\Core\Utility\Token $token
   *   The token system.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\token\TreeBuilder $tokenTreeBuilder
   *   The token tree builder.
   * @param \Drupal\ai\PromptCodeBlockExtractor\PromptCodeBlockExtractor $promptCodeBlockExtractor
   *   The prompt code block extractor.
   */
  public function __construct(
    EntityFieldManagerInterface $entityFieldManager,
    ModuleHandlerInterface $moduleHandler,
    AiAutomatorFieldConfig $aiAutomatorFieldConfig,
    Token $token,
    AccountProxyInterface $currentUser,
    EntityTypeManagerInterface $entityTypeManager,
    TreeBuilder $tokenTreeBuilder,
    PromptCodeBlockExtractorInterface $promptCodeBlockExtractor,
  ) {
    $this->entityFieldManager = $entityFieldManager;
    $this->moduleHandler = $moduleHandler;
    $this->aiAutomatorFieldConfig = $aiAutomatorFieldConfig;
    $this->token = $token;
    $this->currentUser = $currentUser;
    $this->entityTypeManager = $entityTypeManager;
    $this->tokenTreeBuilder = $tokenTreeBuilder;
    $this->promptCodeBlockExtractor = $promptCodeBlockExtractor;
  }

  /**
   * This takes a possible JSON response from a LLM and cleans it up.
   *
   * @param string $response
   *   The response from the LLM.
   *
   * @return array
   *   The cleaned up JSON response.
   */
  public function parseJson($response) {
    // Look for the json start with [ or { and stop with } or ] using regex.
    if (preg_match('/[\[\{].*[\}\]]/s', $response, $matches)) {
      $response = $matches[0];
    }
    // Try to decode.
    $json = json_decode($response, TRUE);
    // Sometimes it doesn't become a valid JSON response, but many.
    if (!is_array($json)) {
      $newJson = [];
      foreach (explode("\n", $response) as $row) {
        if ($row) {
          $parts = json_decode(str_replace("\n", "", $row), TRUE);
          if (is_array($parts)) {
            $newJson = array_merge($newJson, $parts);
          }
        }
      }
      if (!empty($newJson)) {
        $json = $newJson;
      }
    }
    if (isset($json[0]['value'])) {
      $values = [];
      foreach ($json as $val) {
        if (isset($val['value'])) {
          $values[] = $val['value'];
        }
      }
      return $values;
    }
    // Sometimes it sets the wrong key.
    elseif (isset($json[0])) {
      $values = [];
      foreach ($json as $val) {
        if (isset($val[key($val)])) {
          $values[] = $val[key($val)];
        }
        return $values;
      }
    }
    // Sometimes it does not return with values in GPT 3.5.
    elseif (is_array($json) && isset($json[0][0])) {
      $values = [];
      foreach ($json as $vals) {
        foreach ($vals as $val) {
          if (isset($val)) {
            $values[] = $val;
          }
        }
      }
      return $values;
    }
    elseif (isset($json['value'])) {
      return [$json['value']];
    }
    else {
      return [$response['choices'][0]['message']['content']];
    }
    return [];
  }

  /**
   * Adds common LLM parameters to the form.
   *
   * @param string $prefix
   *   The prefix for the form.
   * @param array $form
   *   The form passed by reference.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   */
  public function addCommonLlmParametersFormFields($prefix, array &$form, ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    $form["automator_{$prefix}_temperature"] = [
      '#type' => 'number',
      '#title' => $this->t('Temperature'),
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_automator', "automator_{$prefix}_temperature", 0.5),
      '#description' => $this->t('The temperature of the model, the higher the more creative, the lower the more factual.'),
      '#min' => 0,
      '#max' => 2,
      '#step' => '0.1',
    ];

    $form["automator_{$prefix}_max_tokens"] = [
      '#type' => 'number',
      '#title' => $this->t('Max Tokens'),
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_automator', "automator_{$prefix}_max_tokens", 1024),
      '#description' => $this->t('The maximum number of tokens to generate.'),
    ];

    $form["automator_{$prefix}_top_p"] = [
      '#type' => 'number',
      '#title' => $this->t('AI Top P'),
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_automator', "automator_{$prefix}_top_p", 1),
      '#description' => $this->t('The nucleus sampling probability.'),
      '#min' => 0,
      '#max' => 1,
      '#step' => '0.1',
    ];

    $form["automator_{$prefix}_top_k"] = [
      '#type' => 'number',
      '#title' => $this->t('AI Top K'),
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_automator', "automator_{$prefix}_top_k", 50),
      '#description' => $this->t('The top k sampling probability.'),
      '#min' => 0,
      '#max' => 100,
    ];

    $form["automator_{$prefix}_frequency_penalty"] = [
      '#type' => 'number',
      '#title' => $this->t('Frequency Penalty'),
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_automator', "automator_{$prefix}_frequency_penalty", 0),
      '#description' => $this->t('The frequency penalty.'),
      '#min' => -2,
      '#max' => 2,
    ];

    $form["automator_{$prefix}_presence_penalty"] = [
      '#type' => 'number',
      '#title' => $this->t('Presence Penalty'),
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_automator', "automator_{$prefix}_presence_penalty", 0),
      '#description' => $this->t('The presence penalty.'),
      '#min' => -2,
      '#max' => 2,
    ];
  }

  /**
   * Helper function if the automator needs to load another set of fields.
   *
   * @param Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity type to list on.
   * @param string $type
   *   The field type to get.
   * @param string $target
   *   The target type to get.
   *
   * @return array
   *   The fields found.
   */
  public function getFieldsOfType(ContentEntityInterface $entity, $type, $target = NULL) {
    $fields = $this->entityFieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());
    $names = [];
    foreach ($fields as $fieldDefinition) {
      $fieldTarget = $fieldDefinition->getFieldStorageDefinition()->getSettings()['target_type'] ?? NULL;
      if ($type == $fieldDefinition->getType() && (
        !$target || !$fieldTarget || $fieldTarget == $target)) {
        $names[$fieldDefinition->getName()] = $fieldDefinition->getLabel();
      }
    }
    return $names;
  }

  /**
   * Get all image fields or media fields with image field as options.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to look at.
   *
   * @return array
   *   The image fields.
   */
  public function getImageMediaFields(ContentEntityInterface $entity) {
    $fields = $this->entityFieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());
    $names = [];
    foreach ($fields as $fieldDefinition) {
      $fieldTarget = $fieldDefinition->getFieldStorageDefinition()->getSettings()['target_type'] ?? NULL;
      if ('image' == $fieldDefinition->getType()) {
        $names[$fieldDefinition->getName()] = $fieldDefinition->getLabel();
      }
      if ('entity_reference' == $fieldDefinition->getType() && $fieldTarget == 'media') {
        $bundles = array_keys($fieldDefinition->getSettings()['handler_settings']['target_bundles']) ?? [];
        foreach ($bundles as $bundle) {
          $mediaStorage = $this->entityTypeManager->getStorage('media');
          $mediaTypeInterface = $this->entityTypeManager->getStorage('media_type')->load($bundle);
          /** @var \Drupal\media\Entity\Media $media */
          $media = $mediaStorage->create([
            'name' => 'tmp',
            'bundle' => $bundle,
          ]);
          $mediaSource = $media->getSource();
          $sourceField = $mediaSource->getSourceFieldDefinition($mediaTypeInterface);
          $names[$fieldDefinition->getName() . '--' . $sourceField->getName()] = $fieldDefinition->getLabel() . ' (Media: ' . $mediaTypeInterface->label() . ')';
        }
      }
    }
    return $names;
  }

  /**
   * Base64 encode an image.
   *
   * @param \Drupal\file\FileInterface $imageEntity
   *   The image entity.
   *
   * @return string
   *   The base64 encoded image.
   */
  public function base64EncodeFileEntity(FileInterface $imageEntity) {
    return 'data:' . $imageEntity->getMimeType() . ';base64,' . base64_encode(file_get_contents($imageEntity->getFileUri()));
  }

  /**
   * Helper function to offer a form to joins multiple text values.
   *
   * @param string $id
   *   The id.
   * @param array $form
   *   The form element, passed by reference.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   * @param array $defaultValues
   *   The default values.
   * @param array $extraJoiners
   *   Extra joiners specific to this field, assoc array with key/title.
   * @param string $defaultJoiner
   *   The default joiner.
   */
  public function addJoinerConfigurationFormField($id, array &$form, ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $defaultValues, array $extraJoiners = [], $defaultJoiner = "") {
    $joiners = [
      '' => $this->t("-- Don't join --"),
      ', ' => $this->t('Comma, with space (, )'),
      ' ' => $this->t('Space ( )'),
      '. ' => $this->t('Period, with space (. )'),
      '\n' => $this->t('New line (\n)'),
      '\t' => $this->t('Tab (\t)'),
      '<br />' => $this->t('HTML Break (&#x3c;br />)'),
      '<br /><br />' => $this->t('HTML Double Break (&#x3c;br />&#x3c;br /&#x3e;)'),
      '<hr />' => $this->t('HTML Horizontal Rule (&#x3c;hr />)'),
      ',' => $this->t('Comma (,)'),
      ';' => $this->t('Semicolon (;)'),
      '.' => $this->t('Period (.)'),
      'other' => $this->t('Other'),
    ];
    $joiners = array_merge_recursive($joiners, $extraJoiners);
    $storage = $this->entityTypeManager->getStorage('ai_automator');
    $storage->loadByProperties([
      'entity_type' => $entity->getEntityTypeId(),
      'bundle' => $entity->bundle(),
    ]);

    $form["{$id}_joiner"] = [
      '#type' => 'select',
      '#options' => $joiners,
      '#title' => $this->t('Joiner'),
      '#description' => $this->t('If you do not want multiple values back, this will take all values and join them.'),
      '#default_value' => !empty($fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_automator', "{$id}_joiner", $defaultJoiner)) ? $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_automator', "{$id}_joiner", $defaultJoiner) : $defaultValues["{$id}_joiner"] ?? "",
    ];

    $form["{$id}_joiner_other"] = [
      '#type' => 'textfield',
      '#title' => $this->t('Other Joiner'),
      '#description' => $this->t('If you selected other, please specify the joiner.'),
      '#default_value' => $defaultValues["{$id}_joiner_other"] ?? "",
      '#states' => [
        'visible' => [
          'select[name="' . $id . '_joiner"]' => [
            'value' => 'other',
          ],
        ],
      ],
    ];
  }

  /**
   * Helper function to join if wanted.
   *
   * @param array $values
   *   The values to join.
   * @param string $joiner
   *   The joiner.
   *
   * @return string
   *   The joined string.
   */
  public function joinValues(array $values, $joiner) {
    // Make sure that newline and tab is evaluated.
    $joiner = str_replace(['\n', '\t'], ["\n", "\t"], $joiner);
    return implode($joiner, $values);
  }

  /**
   * Helper function to enable/disable form field tokens from the entity.
   *
   * @param array $form
   *   The form element, passed by reference.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   * @param array $defaultValues
   *   The default values.
   */
  public function addTokenConfigurationToggle(array &$form, $entity, $fieldDefinition, $defaultValues) {
    $form['automator_token_configuration_toggle'] = [
      '#type' => 'checkbox',
      '#title' => 'Dynamic Configuration',
      '#description' => $this->t('If you want to set configuration values based on the entity, this will expose token fields for this.'),
      '#default_value' => $defaultValues['automator_token_configuration_toggle'] ?? FALSE,
    ];
  }

  /**
   * Helper function to offer a form field as tokens from the entity.
   *
   * @param string $id
   *   The id.
   * @param array $form
   *   The form element, passed by reference.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   * @param string $wrapper
   *   If its under a wrapper.
   * @param int $weight
   *   Any added weight.
   */
  public function addTokenConfigurationFormField($id, array &$form, ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, $wrapper = "", $weight = 0) {
    $title = $form[$id]['#title'] ?? $id;
    if ($wrapper) {
      $title = $form[$wrapper][$id]['#title'];
    }

    $mergeForm["{$id}_override"] = [
      '#type' => 'details',
      '#title' => $this->t(':word Token', [
        ':word' => $title,
      ]),
      '#states' => [
        'visible' => [
          'input[name="automator_token_configuration_toggle"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    if ($weight) {
      $mergeForm["{$id}_override"]['#weight'] = $weight;
    }

    $mergeForm["{$id}_override"]["{$id}_token"] = [
      '#type' => 'textfield',
      '#title' => $this->t(':word Token', [
        ':word' => $title,
      ]),
      '#description' => $this->t('If you want to set this value based on a token, this will overwritten the set value if it exists.'),
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_automator', "{$id}_token", ''),
    ];

    $mergeForm["{$id}_override"]['token_help'] = $this->tokenTreeBuilder->buildRenderable([
      $this->aiAutomatorFieldConfig->getEntityTokenType($entity->getEntityTypeId()),
      'current-user',
    ]);

    if ($wrapper) {
      $newForm[$wrapper] = $mergeForm;
    }
    else {
      $newForm = $mergeForm;
    }

    $form = array_merge_recursive($form, $newForm);
  }

  /**
   * Get override value.
   *
   * @param string $id
   *   Key to get value from.
   * @param array $automatorConfig
   *   The config.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param mixed $default
   *   A default value if nothing is found.
   *
   * @return mixed
   *   The value.
   */
  public function getConfigValue($id, $automatorConfig, $entity, $default = NULL) {
    $configValue = $automatorConfig[$id] ?? $default;
    // Return if there is no override.
    if (empty($automatorConfig["{$id}_override"])) {
      return $configValue;
    }
    $entityValue = $this->token->replace($automatorConfig["{$id}_override"], [
      $this->aiAutomatorFieldConfig->getEntityTokenType($entity->getEntityTypeId()) => $entity,
      'user' => $this->currentUser,
    ]);
    return !$entityValue && $configValue ? $configValue : $entityValue;
  }

  /**
   * Get possible text formats for a drop down.
   *
   * @return array
   *   The text formats.
   */
  public function getTextFormatsOptions() {
    $formats = $this->entityTypeManager->getStorage('filter_format')->loadMultiple();
    $options = [
      '' => $this->t('-- None/User Based --'),
    ];

    foreach ($formats as $format) {
      $options[$format->id()] = $format->label();
    }
    return $options;
  }

  /**
   * Calculate text format.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   *
   * @return string|null
   *   The format.
   */
  public function calculateTextFormat(FieldDefinitionInterface $fieldDefinition): ?string {
    $allFormats = $this->entityTypeManager->getStorage('filter_format')->loadMultiple();
    // Maybe no formats are available.
    if (empty($allFormats)) {
      return NULL;
    }
    $formatsAllowed = $fieldDefinition->getSetting('allowed_formats');
    // If no formats are allowed, return NULL.
    if ($formatsAllowed === NULL) {
      return NULL;
    }
    // All formats are allowed.
    if (empty($formatsAllowed)) {
      $formatsAllowed = array_keys($allFormats);
    }
    foreach ($formatsAllowed as $format) {
      // Check if the user has access to the format.
      if (isset($allFormats[$format]) && $allFormats[$format]->access('use')) {
        return $format;
      }
    }
    // User does not have access to any format.
    return NULL;
  }

  /**
   * Get image styles preprocess field.
   *
   * This function is used to get the image styles for preprocess field.
   * This is very useful when we want to manipulate some images before sending
   * them to the AI model.
   *
   * @param bool $none
   *   If we want to add a none option.
   *
   * @return array
   *   An array of all image styles.
   */
  public function getImageStyles($none = TRUE) {
    if ($this->entityTypeManager->hasDefinition('image_style')) {
      $imageStyles = $this->entityTypeManager->getStorage('image_style')->loadMultiple();
    }
    else {
      $imageStyles = [];
    }
    $imageStylesOptions = [];
    if ($none) {
      $imageStylesOptions[''] = $this->t('-- None --');
    }
    foreach ($imageStyles as $imageStyle) {
      $imageStylesOptions[$imageStyle->id()] = $imageStyle->label();
    }
    return $imageStylesOptions;
  }

  /**
   * Get the prompt code block extractor.
   *
   * @return \Drupal\ai\Service\PromptCodeBlockExtractor\PromptCodeBlockExtractorInterface
   *   The prompt code block extractor.
   */
  public function getPromptCodeBlockExtractor() {
    return $this->promptCodeBlockExtractor;
  }

  /**
   * Preprocess the image style.
   *
   * @param \Drupal\file\FileInterface $imageEntity
   *   The image entity.
   * @param string $imageStyle
   *   The image style.
   *
   * @return \Drupal\file\FileInterface
   *   A temporary image entity.
   */
  public function preprocessImageStyle(FileInterface $imageEntity, $imageStyle) {
    /** @var \Drupal\image\ImageStyleInterface $imageStyle */
    $imageStyle = $this->entityTypeManager->getStorage('image_style')->load($imageStyle);
    $uri = $imageStyle->buildUri($imageEntity->getFileUri());
    $imageStyle->createDerivative($imageEntity->getFileUri(), $uri);
    // Set the file status to temporary.
    $file = $this->entityTypeManager->getStorage('file')->create([
      'uri' => $uri,
      'status' => 0,
    ]);
    $file->save();
    return $file;
  }

  /**
   * Get or generate taxonomy in vocabulary.
   *
   * @param string $vocabulary
   *   The vocabulary.
   * @param string $label
   *   The label.
   *
   * @return \Drupal\taxonomy\Entity\Term
   *   The term.
   */
  public function getOrGenerateTaxonomyTerm($vocabulary, $label) {
    $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');
    $terms = $termStorage->loadByProperties([
      'name' => $label,
      'vid' => $vocabulary,
    ]);
    if ($terms) {
      return reset($terms);
    }
    $term = $termStorage->create([
      'name' => $label,
      'vid' => $vocabulary,
    ]);
    $term->save();
    return $term;
  }

  /**
   * Get vocabularies for a entity reference field.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param string $fieldName
   *   The field name.
   *
   * @return array
   *   The vocabularies.
   */
  public function getVocabulariesFromField($entityType, $bundle, $fieldName) {
    $fieldStorage = $this->entityFieldManager->getFieldDefinitions($entityType, $bundle)[$fieldName];
    $vocabularies = [];
    foreach ($fieldStorage->getSetting('handler_settings')['target_bundles'] as $vocabulary) {
      if ($vocabulary) {
        $vocabularies[] = $vocabulary;
      }
    }
    return $vocabularies;
  }

  /**
   * Get the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  public function entityTypeManager() {
    return $this->entityTypeManager;
  }

}
