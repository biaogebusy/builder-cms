<?php

namespace Drupal\Tests\eck\Unit\TestDoubles;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;

/**
 * Dummy implementation of FieldTypePluginManagerInterface.
 */
class FieldTypePluginManagerDummy implements FieldTypePluginManagerInterface {

  /**
   * {@inheritdoc}
   */
  public function getCategories() {
    // Stub.
  }

  /**
   * {@inheritdoc}
   */
  public function getSortedDefinitions(array $definitions = NULL) {
    // Stub.
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupedDefinitions(array $definitions = NULL) {
    // Stub.
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinition($plugin_id, $exception_on_invalid = TRUE) {
    // Stub.
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    // Stub.
  }

  /**
   * {@inheritdoc}
   */
  public function hasDefinition($plugin_id) {
    // Stub.
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    // Stub.
  }

  /**
   * {@inheritdoc}
   */
  public function createFieldItemList(FieldableEntityInterface $entity, $field_name, $values = NULL) {
    // Stub.
  }

  /**
   * {@inheritdoc}
   */
  public function createFieldItem(FieldItemListInterface $items, $index, $values = NULL) {
    // Stub.
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFieldSettings($type) {
    // Stub.
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultStorageSettings($type) {
    // Stub.
  }

  /**
   * {@inheritdoc}
   */
  public function getUiDefinitions() {
    // Stub.
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginClass($type) {
    // Stub.
  }

  /**
   * {@inheritdoc}
   */
  public function getInstance(array $options) {
    // Stub.
  }

  /**
   * {@inheritdoc}
   */
  public function getPreconfiguredOptions($field_type) {
    // Stub.
  }

}
