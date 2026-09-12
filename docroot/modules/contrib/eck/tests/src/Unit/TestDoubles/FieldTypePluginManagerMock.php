<?php

namespace Drupal\Tests\eck\Unit\TestDoubles;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Mock implementation of FieldTypePluginManagerInterface.
 */
class FieldTypePluginManagerMock extends FieldTypePluginManagerDummy {

  /**
   * {@inheritdoc}
   */
  public function getDefaultStorageSettings($type) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFieldSettings($type) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldSettingsSummary(FieldDefinitionInterface $field_definition, array $settings = []): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageSettingsSummary(FieldStorageDefinitionInterface $storage_definition, array $settings = []): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeUiDefinitions(string $entity_type_id): array {
    return [];
  }

}
