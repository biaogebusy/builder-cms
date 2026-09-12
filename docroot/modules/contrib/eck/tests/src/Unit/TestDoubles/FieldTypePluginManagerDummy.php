<?php

namespace Drupal\Tests\eck\Unit\TestDoubles;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;

/**
 * Dummy implementation of FieldTypePluginManagerInterface.
 */
class FieldTypePluginManagerDummy implements FieldTypePluginManagerInterface {

  /**
   * {@inheritdoc}
   */
  public function getCategories() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSortedDefinitions(?array $definitions = NULL) {
    // Stub.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupedDefinitions(?array $definitions = NULL) {
    // Stub.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinition($plugin_id, $exception_on_invalid = TRUE) {
    // Stub.
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    // Stub.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function hasDefinition($plugin_id) {
    // Stub.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    // Stub.
    return new \stdClass();
  }

  /**
   * {@inheritdoc}
   */
  public function createFieldItemList(FieldableEntityInterface $entity, $field_name, $values = NULL) {
    // Stub.
    return '\Drupal\Core\Field\FieldItemListInterface';
  }

  /**
   * {@inheritdoc}
   */
  public function createFieldItem(FieldItemListInterface $items, $index, $values = NULL) {
    // Stub.
    return '\Drupal\Core\Field\FieldItemInterface';
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFieldSettings($type) {
    // Stub.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultStorageSettings($type) {
    // Stub.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getUiDefinitions() {
    // Stub.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginClass($type) {
    // Stub.
    return '\Drupal\field\Plugin\Field\FieldType\BaseFieldType';
  }

  /**
   * {@inheritdoc}
   */
  public function getInstance(array $options) {
    // Stub.
    return new \stdClass();
  }

  /**
   * {@inheritdoc}
   */
  public function getPreconfiguredOptions($field_type) {
    // Stub.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageSettingsSummary(FieldStorageDefinitionInterface $storage_definition): array {
    // Stub.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldSettingsSummary(FieldDefinitionInterface $field_definition): array {
    // Stub.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeUiDefinitions(string $entity_type_id): array {
    // Stub.
    return [];
  }

}
