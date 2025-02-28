<?php

namespace Drupal\entity_print_test\Plugin\EntityPrint\PrintEngine;

use Drupal\entity_print\Plugin\PrintEngineBase;

/**
 * Unavailable print engine for testing.
 *
 * @PrintEngine(
 *   id = "not_available_print_engine",
 *   label = @Translation("Not Available Print Engine"),
 *   export_type = "pdf"
 * )
 */
class NotAvailablePrintEngine extends PrintEngineBase {

  /**
   * {@inheritdoc}
   */
  public function send($filename, $force_download = TRUE) {}

  /**
   * {@inheritdoc}
   */
  public function getBlob() {}

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
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getPrintObject() {}

}
