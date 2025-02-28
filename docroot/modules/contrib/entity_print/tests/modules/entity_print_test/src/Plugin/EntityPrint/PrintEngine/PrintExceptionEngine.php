<?php

namespace Drupal\entity_print_test\Plugin\EntityPrint\PrintEngine;

use Drupal\entity_print\Plugin\PrintEngineBase;
use Drupal\entity_print\PrintEngineException;

/**
 * A test print engine that throws an exception.
 *
 * @PrintEngine(
 *   id = "print_exception_engine",
 *   label = @Translation("Print Exception Engine"),
 *   export_type = "pdf"
 * )
 */
class PrintExceptionEngine extends PrintEngineBase {

  /**
   * {@inheritdoc}
   */
  public function send($filename, $force_download = TRUE) {
    throw new PrintEngineException('Exception thrown by PrintExceptionEngine');
  }

  /**
   * {@inheritdoc}
   */
  public function getBlob() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getError() {}

  /**
   * {@inheritdoc}
   */
  public function addPage($content) {}

  /**
   * {@inheritdoc}
   */
  public static function dependenciesAvailable() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getPrintObject() {}

}
