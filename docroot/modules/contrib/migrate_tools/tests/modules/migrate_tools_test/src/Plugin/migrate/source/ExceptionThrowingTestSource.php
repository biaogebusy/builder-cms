<?php

namespace Drupal\migrate_tools_test\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;

/**
 * A simple migrate source for testing exception handling.
 *
 * @MigrateSource(
 *   id = "migrate_exception_source_test",
 *   source_module = "migrate_tools_test"
 * )
 */
class ExceptionThrowingTestSource extends SourcePluginBase {

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator() {
    return new \ArrayIterator();
  }

  /**
   * {@inheritdoc}
   */
  public function rewind() {
    throw new \Exception('Rewind Failure');
  }

}
