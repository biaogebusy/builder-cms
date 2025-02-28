<?php

namespace Drupal\Tests\eck\Unit\TestDoubles;

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

}
