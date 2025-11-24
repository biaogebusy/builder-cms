<?php

namespace Drupal\views_data_export;

/**
 * Drupal adapter for batch processing.
 */
class BatchProcessingAdapterDrupal implements BatchProcessingAdapterInterface {

  /**
   * {@inheritdoc}
   */
  public function batchProcess() {
    return batch_process();
  }

  /**
   * {@inheritdoc}
   */
  public function getFinishedCallback(string $caller) {
    return [$caller, 'finishBatch'];
  }

}
