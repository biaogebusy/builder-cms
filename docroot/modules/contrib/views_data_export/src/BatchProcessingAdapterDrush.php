<?php

namespace Drupal\views_data_export;

/**
 * Drush adapter for batch processing.
 */
class BatchProcessingAdapterDrush implements BatchProcessingAdapterInterface {

  /**
   * {@inheritdoc}
   */
  public function batchProcess() {
    return drush_backend_batch_process();
  }

  /**
   * {@inheritdoc}
   */
  public function getFinishedCallback(string $caller) {
    return [static::class, 'finishBatchDrush'];
  }

  /**
   * Implements callback for batch finish when running from Drush.
   */
  public static function finishBatchDrush($success, array $results, array $operations) {
    // We don't need to do anything here. But without this callback, Drush does
    // not pass us the results correctly.
  }

}
