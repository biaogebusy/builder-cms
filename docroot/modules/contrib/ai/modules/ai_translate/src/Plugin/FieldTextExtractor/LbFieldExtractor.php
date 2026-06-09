<?php

namespace Drupal\ai_translate\Plugin\FieldTextExtractor;

use Drupal\ai_translate\FieldTextExtractorPluginManager;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_translate\Attribute\FieldTextExtractor;
use Drupal\ai_translate\TextExtractorInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\layout_builder\Plugin\Block\InlineBlock;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A field text extractor plugin for layout builder content.
 *
 * This plugin is only usable when the block_content module is enabled.
 *
 * The only supported section type is inline block.
 * Make sure that all content block fields displayed in layout builder
 * are configured to be translatable.
 */
#[FieldTextExtractor(
  id: "layout_builder",
  label: new TranslatableMarkup('layout_builder'),
  field_types: [
    'layout_section',
  ]
)]
class LbFieldExtractor extends FieldExtractorBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

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
   * Session storage manager.
   *
   * @var \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface
   */
  protected SectionStorageManagerInterface $sectionStorageManager;


  /**
   * Text extractor.
   *
   * @var \Drupal\ai_translate\TextExtractorInterface
   */
  protected TextExtractorInterface $textExtractor;

  /**
   * The FieldTextExtractor plugin manager.
   *
   * @var \Drupal\ai_translate\FieldTextExtractorPluginManager
   */
  protected FieldTextExtractorPluginManager $extractorManager;

  /**
   * The block content storage service.
   *
   * @var \Drupal\Core\Entity\RevisionableStorageInterface|null
   */
  protected ?RevisionableStorageInterface $blockStorage = NULL;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Logger channel for this class.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The UUID service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected UuidInterface $uuid;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->config = $container->get('config.factory')?->get('ai_translate.settings');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->sectionStorageManager = $container->get('plugin.manager.layout_builder.section_storage');
    $instance->textExtractor = $container->get('ai_translate.text_extractor');
    $instance->extractorManager = $container->get('plugin.manager.text_extractor');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->logger = $container->get('logger.factory')?->get('ai_translate');
    $instance->uuid = $container->get('uuid');
    try {
      $instance->blockStorage = $instance->entityTypeManager
        ->getStorage('block_content');
    }
    catch (PluginNotFoundException) {
      return $instance;
    }
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function extract(ContentEntityInterface $entity, string $fieldName): array {
    if (!isset($this->blockStorage)) {
      return [];
    }
    if ($entity->get($fieldName)->isEmpty()) {
      return [];
    }
    $textMeta = [];
    /** @var \Drupal\layout_builder\Field\LayoutSectionItemList $sectionList */
    $sectionList = $entity->get($fieldName);
    foreach ($sectionList->getSections() as $section) {
      foreach ($section->getComponents() as $component) {
        $plugin = $component->getPlugin();
        if (!$plugin instanceof InlineBlock) {
          continue;
        }
        // Mimic behavior of (protected) InlineBlock::getEntity().
        $blockEntity = NULL;
        $blockConfig = $plugin->getConfiguration();
        if (!empty($blockConfig['block_serialized'])) {
          $blockEntity = unserialize($blockConfig['block_serialized'], ['allowed_classes' => FALSE]);
        }
        elseif (!empty($blockConfig['block_revision_id'])) {
          $blockEntity = $this->blockStorage->loadRevision($blockConfig['block_revision_id']);
        }
        else {
          $blockEntity = $this->blockStorage->create([
            'type' => $plugin->getDerivativeId(),
            'reusable' => FALSE,
          ]);
        }
        if (!$blockEntity instanceof ContentEntityInterface) {
          continue;
        }
        foreach ($this->textExtractor->extractTextMetadata($blockEntity) as $subMeta) {
          $textMeta[] = ['delta' => $blockEntity->uuid()] + $subMeta;
        }
      }
    }
    return $textMeta;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue(ContentEntityInterface $entity, string $fieldName, array $textMeta) : void {
    $translationLanguage = $entity->language()->getId();
    if ($this->moduleHandler->moduleExists('layout_builder_at')) {
      // Layout Builder has been set up for asymmetric block translation.
      // Use this approach as well for AI translations.
      $this->asymmetricLayoutBuilderBlockTranslation($entity, $fieldName, $translationLanguage, $textMeta);
    }
    else {
      // Layout Builder has been set up for symmetric block translation.
      // Use this approach as well for AI translations.
      $this->symmetricLayoutBuilderBlockTranslation($translationLanguage, $textMeta);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function shouldExtract(ContentEntityInterface $entity, FieldConfigInterface $fieldDefinition): bool {
    return isset($this->blockStorage);
  }

  /**
   * Translate Layout Builder blocks symmetrically.
   *
   * @param string $translationLanguage
   *   The translation language to translate into.
   * @param array $textMeta
   *   The translated data.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function symmetricLayoutBuilderBlockTranslation(string $translationLanguage, array $textMeta): void {
    foreach ($this->blockStorage->loadByProperties(['uuid' => array_keys($textMeta)]) as $blockEntity) {
      // @todo Decide if/when we should update existing translation.
      $blockEntity = $blockEntity->hasTranslation($translationLanguage)
        ? $blockEntity->getTranslation($translationLanguage)
        : $blockEntity->addTranslation($translationLanguage, $blockEntity->toArray());

      foreach ($textMeta[$blockEntity->uuid()] as $subFieldName => $subValue) {
        $field = $blockEntity->get($subFieldName);
        $extractor = $this->extractorManager->getExtractor(
          $field->getFieldDefinition()->getType()
        );
        // This shouldn't happen.
        if (!$extractor) {
          continue;
        }
        $extractor->setValue($blockEntity, $subFieldName, $subValue);
      }
      $this->blockStorage->save($blockEntity);
    }
  }

  /**
   * Translate Layout Builder blocks asymmetrically.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param string $fieldName
   *   The name of the field to translate.
   * @param string $translationLanguage
   *   The translation language to translate into.
   * @param array $textMeta
   *   The translated data.
   *
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  protected function asymmetricLayoutBuilderBlockTranslation(ContentEntityInterface $entity, string $fieldName, string $translationLanguage, array $textMeta): void {
    $sourceEntity = $entity->getUntranslated();
    $originalSections = $sourceEntity->get($fieldName)->getValue();
    $duplicatedSections = [];

    foreach ($originalSections as $sectionData) {
      $section = $sectionData['section'];
      $duplicatedSection = clone $section;

      foreach ($duplicatedSection->getComponents() as $componentId => $component) {
        $blockEntity = NULL;
        $configuration = $component->get('configuration');

        if (isset($configuration['block_uuid'])) {
          $blockEntity = $this->blockStorage->loadByProperties(['uuid' => $configuration['block_uuid']]);
          $blockEntity = !empty($blockEntity) ? current($blockEntity) : NULL;
        }
        elseif (isset($configuration['block_revision_id'])) {
          $blockEntity = $this->blockStorage->loadRevision($configuration['block_revision_id']);
        }
        elseif (isset($configuration['block_id'])) {
          $blockEntity = $this->blockStorage->load($configuration['block_id']);
        }

        if ($blockEntity) {
          $clonedBlock = $blockEntity->createDuplicate();
          $clonedBlock->set('uuid', $this->uuid->generate());
          $clonedBlock->set('langcode', $translationLanguage);

          foreach ($textMeta as $uuid => $translations) {
            if ($uuid === $blockEntity->uuid()) {
              // Insert translated metadata into block entity.
              foreach ($translations as $translationFieldName => $translationFieldValue) {
                $field = $clonedBlock->get($translationFieldName);
                $extractor = $this->extractorManager->getExtractor(
                  $field->getFieldDefinition()->getType()
                );
                // This shouldn't happen.
                if (!$extractor) {
                  continue;
                }
                $extractor->setValue($clonedBlock, $translationFieldName, $translationFieldValue);
              }
            }
          }

          try {
            $clonedBlock->save();
          }
          catch (\Exception $e) {
            $this->logger->error('Failed to save cloned block: @message', ['@message' => $e->getMessage()]);
          }

          // Update the component configuration with the cloned block's IDs.
          if (isset($configuration['block_uuid'])) {
            $configuration['block_uuid'] = $clonedBlock->uuid();
          }
          $configuration['block_id'] = $clonedBlock->id();
          $configuration['block_revision_id'] = $clonedBlock->getRevisionId();
          $component->setConfiguration($configuration);
          // Generate a new UUID for the component to avoid conflicts.
          $duplicatedSection->getComponent($componentId)->set('uuid', $this->uuid->generate());
        }
      }

      $duplicatedSections[] = $duplicatedSection;
    }

    $entity->get($fieldName)->setValue($duplicatedSections);

    try {
      $entity->save();
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to save translated entity: @message', ['@message' => $e->getMessage()]);
    }
  }

}
