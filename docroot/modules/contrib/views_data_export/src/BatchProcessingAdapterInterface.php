<?php

namespace Drupal\views_data_export;

/**
 * Interface for batch processing adapters.
 *
 * This interface defines the methods required for batch processing in the
 * Views Data Export module, allowing for different implementations depending
 * on the context (e.g., Drupal or Drush).
 */
interface BatchProcessingAdapterInterface {

  /**
   * Process the batch.
   */
  public function batchProcess();

  /**
   * Returns the callback to be used for batch processing completion.
   *
   * @param string $caller
   *   The name of the class that will handle the batch completion.
   *
   * @return callable
   *   A callable that will be invoked when the batch processing is finished.
   */
  public function getFinishedCallback(string $caller);

}
