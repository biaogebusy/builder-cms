<?php

namespace Drupal\views_bulk_operations\Service;

/**
 * Defines Views Bulk Operations action processor.
 */
interface ViewsBulkOperationsActionProcessorInterface {

  /**
   * Set values.
   *
   * @param array $view_data
   *   Data concerning the view that will be processed.
   * @param mixed $view
   *   The current view object or NULL.
   */
  public function initialize(array $view_data, $view = NULL);

  /**
   * Get the current processing entity queue.
   *
   * @param array $view_data
   *   Data concerning the view that will be processed.
   *
   * @return array
   *   Array of entity labels.
   */
  public function getLabels(array $view_data);

  /**
   * Get full list of items from a specific view page.
   *
   * @param int $page
   *   Results page number.
   *
   * @return array
   *   Array of result data arrays.
   */
  public function getPageList($page);

  /**
   * Populate entity queue for processing.
   *
   * @param array $data
   *   Data concerning the view that will be processed.
   * @param array $context
   *   Batch API context.
   */
  public function populateQueue(array $data, array &$context = []);

  /**
   * Process results.
   */
  public function process();

  /**
   * Helper function for processing results from view data.
   *
   * @param array $data
   *   Data concerning the view that will be processed.
   * @param mixed $view
   *   The current view object or NULL.
   */
  public function executeProcessing(array &$data, $view = NULL);

}
