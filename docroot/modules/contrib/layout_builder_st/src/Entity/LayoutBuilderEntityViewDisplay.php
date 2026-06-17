<?php

namespace Drupal\layout_builder_st\Entity;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay as CoreLayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Entity\LayoutEntityDisplayInterface;
use Drupal\layout_builder_st\Plugin\SectionStorage\OverridesSectionStorage;

/**
 * Layout Entity Display overridden to add translation field.
 */
final class LayoutBuilderEntityViewDisplay extends CoreLayoutBuilderEntityViewDisplay implements LayoutEntityDisplayInterface {

  /**
   * {@inheritdoc}
   */
  protected function addSectionField($entity_type_id, $bundle, $field_name) {
    parent::addSectionField($entity_type_id, $bundle, $field_name);
    $this->addTranslationField($entity_type_id, $bundle, OverridesSectionStorage::TRANSLATED_CONFIGURATION_FIELD_NAME);
  }

  /**
   * Adds a layout translation field to a given bundle.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The bundle.
   * @param string $field_name
   *   The name for the translation field.
   */
  protected function addTranslationField($entity_type_id, $bundle, $field_name) {
    $field = FieldConfig::loadByName($entity_type_id, $bundle, $field_name);
    if (!$field) {
      $field_storage = FieldStorageConfig::loadByName($entity_type_id, $field_name);
      if (!$field_storage) {
        $field_storage = FieldStorageConfig::create([
          'entity_type' => $entity_type_id,
          'field_name' => $field_name,
          'type' => 'layout_translation',
          'locked' => TRUE,
        ]);
        $field_storage->setTranslatable(TRUE);
        $field_storage->save();
      }

      $field = FieldConfig::create([
        'field_storage' => $field_storage,
        'bundle' => $bundle,
        'label' => new TranslatableMarkup('Layout Labels'),
      ]);
      $field->save();
    }
  }

}
