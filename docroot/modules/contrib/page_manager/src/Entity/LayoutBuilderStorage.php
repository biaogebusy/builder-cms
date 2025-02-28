<?php

namespace Drupal\page_manager\Entity;

use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Entity\EntityInterface;
use Drupal\layout_builder\Section;

/**
 * Provides storage for page manager entities that have layouts.
 */
class LayoutBuilderStorage extends ConfigEntityStorage {

  /**
   * {@inheritdoc}
   */
  protected function mapToStorageRecord(EntityInterface $entity) {
    $record = parent::mapToStorageRecord($entity);

    if (!empty($record['variant_settings']['sections'])) {
      $record['variant_settings']['sections'] = array_map(function (Section $section) {
        return $section->toArray();
      }, $record['variant_settings']['sections']);
    }
    return $record;
  }

  /**
   * {@inheritdoc}
   */
  protected function mapFromStorageRecords(array $records) {
    foreach ($records as &$record) {
      if (!empty($record['variant_settings']['sections'])) {
        $sections = &$record['variant_settings']['sections'];
        $sections = array_map([Section::class, 'fromArray'], $sections);
      }
    }
    return parent::mapFromStorageRecords($records);
  }

}
