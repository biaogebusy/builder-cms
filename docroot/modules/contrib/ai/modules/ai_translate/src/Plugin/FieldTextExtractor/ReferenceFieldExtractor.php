<?php

namespace Drupal\ai_translate\Plugin\FieldTextExtractor;

use Drupal\ai_translate\FieldTextExtractorPluginManagerInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\ai_translate\Attribute\FieldTextExtractor;
use Drupal\ai_translate\ConfigurableFieldTextExtractorInterface;
use Drupal\ai_translate\TextExtractorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A field text extractor plugin for entity reference fields.
 */
#[FieldTextExtractor(
  id: "entity_reference",
  label: new TranslatableMarkup('Entity reference'),
  field_types: [
    'entity_reference',
    'entity_reference_revisions',
  ]
)]
class ReferenceFieldExtractor extends FieldExtractorBase implements ConfigurableFieldTextExtractorInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * Translatability options for entity reference fields.
   */
  const TRANSLATE_REFERENCE_YES = 'yes';

  const TRANSLATE_REFERENCE_NO = 'no';

  const TRANSLATE_REFERENCE_DEFAULT = 'default';

  /**
   * Module configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Text extractor.
   *
   * @var \Drupal\ai_translate\TextExtractorInterface
   */
  protected TextExtractorInterface $textExtractor;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The field text extractor plugin manager.
   *
   * @var \Drupal\ai_translate\FieldTextExtractorPluginManagerInterface
   */
  protected FieldTextExtractorPluginManagerInterface $extractorManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->config = $container->get('config.factory')
      ->get('ai_translate.settings');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->textExtractor = $container->get('ai_translate.text_extractor');
    $instance->logger = $container->get('logger.factory')->get('ai_translate');
    $instance->extractorManager = $container->get('plugin.manager.text_extractor');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function extract(ContentEntityInterface $entity, string $fieldName): array {
    // Static variable to track recursion depth.
    static $depth = 0;
    $maxDepth = (int) $this->config->get('entity_reference_depth') ?? 1;

    // Return empty if the maximum depth is reached.
    if ($maxDepth && $depth > $maxDepth) {
      return [];
    }

    if ($entity->get($fieldName)->isEmpty()) {
      return [];
    }

    // Increment depth at the start of processing.
    $depth++;
    $textMeta = [];
    foreach ($entity->get($fieldName)->referencedEntities() as $delta => $subEntity) {
      if ($subEntity instanceof ContentEntityInterface) {
        foreach ($this->textExtractor->extractTextMetadata($subEntity) as $subMeta) {
          $textMeta[] = ['delta' => $delta] + $subMeta;
        }
      }
    }
    // Decrement depth after processing.
    $depth--;

    return $textMeta;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue(ContentEntityInterface $entity, string $fieldName, array $textMeta): void {
    // Get the entity in the source language.
    $entity_in_source_language = $entity->getUntranslated();

    // Check if the field exists and has values before proceeding.
    if ($entity_in_source_language->get($fieldName)->isEmpty()) {
      return;
    }

    $newValue = [];
    // Get the referenced entities based on the source language.
    $referencedEntities = $entity_in_source_language->get($fieldName)->referencedEntities();
    $translationLanguage = $entity->language()->getId();

    foreach ($textMeta as $delta => $singleValue) {
      // Ensure the referenced entity exists.
      if (!isset($referencedEntities[$delta])) {
        continue;
      }

      $referencedEntity = $referencedEntities[$delta];

      // Translate referenced entity.
      if ($referencedEntity->isTranslatable()) {
        $this->translateReferencedEntity($referencedEntity, $singleValue, $translationLanguage);

        try {
          // Save the updated referenced entity.
          $referencedEntity->save();
        }
        catch (\Throwable $e) {
          $this->logger->error('Unexpected error while saving referenced entity @delta. Type: @type, Message: @message, File: @file, Line: @line', [
            '@delta' => $referencedEntity->id(),
            '@type' => get_class($e),
            '@message' => $e->getMessage(),
            '@file' => $e->getFile(),
            '@line' => $e->getLine(),
          ]);

          continue;
        }
      }
      $newValue[$delta] = ['entity' => $referencedEntity];
    }

    // Set the updated values back on the entity.
    $entity->set($fieldName, $newValue);
  }

  /**
   * Translate the referenced entity.
   */
  protected function translateReferencedEntity(ContentEntityInterface $entity, array $fieldsMeta, string $language): void {

    // Check if the entity has the translation, create it if not.
    if (!$entity->hasTranslation($language)) {
      $translatedEntity = $entity->addTranslation($language, $entity->toArray());
    }
    else {
      $translatedEntity = $entity->getTranslation($language);
    }

    // If entity_reference_depth limits have not yet been reached, the fields
    // on this referenced entity will have been extracted as well by their
    // appropriate FieldTextExtractor plugin. Set the translated values on the
    // translated referenced entity now.
    // Fields on the referenced entity that are not translatable, will not be
    // in the extracted data, and remain as-is on the translation.
    foreach ($fieldsMeta as $subFieldName => $subFieldValue) {
      // Get the field definition to check the subfield type.
      $fieldDefinition = $entity->get($subFieldName)->getFieldDefinition();
      // Get the FieldTextExtractor plugin that is relevant for this field type.
      // Pass along the translated referenced entity, so the relevant extractor
      // can set translated values on it.
      $translatedEntity->set($subFieldName, $entity->get($subFieldName)->getValue());
      $extractor = $this->extractorManager->getExtractor(
        $fieldDefinition->getType()
      );
      // This shouldn't happen.
      if (!$extractor) {
        continue;
      }
      // Set the AI translated value on this specific field on the referenced
      // entity.
      $extractor->setValue($translatedEntity, $subFieldName, $subFieldValue);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function shouldExtract(ContentEntityInterface $entity, FieldConfigInterface $fieldDefinition): bool {
    $targetType = $fieldDefinition->getFieldStorageDefinition()->getSetting('target_type');
    // Extract only fields of content entities.
    if (!\in_array(ContentEntityInterface::class, class_implements(
      $this->entityTypeManager->getDefinition($targetType)->getClass()))) {
      return FALSE;
    }
    $fieldSetting = $fieldDefinition->getThirdPartySetting('ai_translate',
      'translate_references', self::TRANSLATE_REFERENCE_DEFAULT);
    return match ($fieldSetting) {
      self::TRANSLATE_REFERENCE_YES => TRUE,
      self::TRANSLATE_REFERENCE_NO => FALSE,
      default => $this->entityTypeTranslatedDefault($targetType),
    };
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(FieldConfigInterface $entity, FormStateInterface $form_state, array &$completeForm = []): array {
    $subform = [];
    $subform['translate_references'] = [
      '#type' => 'radios',
      '#title' => $this->t('Translate referenced entities'),
      '#options' => [
        self::TRANSLATE_REFERENCE_YES => $this->t('Yes'),
        self::TRANSLATE_REFERENCE_NO => $this->t('No'),
        self::TRANSLATE_REFERENCE_DEFAULT => $this->t('Use default'),
      ],
      '#default_value' => $entity->getThirdPartySetting('ai_translate',
          'translate_references') ?? self::TRANSLATE_REFERENCE_DEFAULT,
    ];
    $targetType = $entity->getFieldStorageDefinition()->getSetting('target_type');
    $default = $this->entityTypeTranslatedDefault($targetType);
    $subform['translate_references']['#description'] =
      $this->t('Current default for @entity_type is @default', [
        '@entity_type' => $targetType,
        '@default' => $default ? $this->t('Yes') : $this->t('No'),
      ]);
    $subform['description'] = [
      '#type' => 'link',
      '#title' => $this->t('Change defaults'),
      '#url' => Url::fromRoute('ai_translate.settings_form'),
    ];
    return $subform;
  }

  /**
   * {@inheritdoc}
   */
  public function submitFieldSettingForm(FieldConfigInterface $entity, FormStateInterface $form_state, array &$completeForm = []): void {
    $pluginValues = $form_state->getValue([
      'third_party_settings',
      'ai_translate',
      'entity_reference',
    ]);
    $entity->setThirdPartySetting('ai_translate', 'translate_references',
      $pluginValues['translate_references']);
  }

  /**
   * Get default AI translatability for an entity type.
   *
   * @param string $entityTypeId
   *   Entity type ID.
   *
   * @return bool
   *   TRUE to translate referenced entities of this type by default.
   */
  protected function entityTypeTranslatedDefault(string $entityTypeId): bool {
    $defaults = $this->config->get('reference_defaults') ?? [];

    // Default is to not translate entities of an unknown type.
    return \in_array($entityTypeId, $defaults);
  }

}
