<?php

namespace Drupal\views_bulk_operations;

use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Defines module Batch API methods.
 */
class ViewsBulkOperationsBatch {

  /**
   * Translation function wrapper.
   *
   * @see \Drupal\Core\StringTranslation\TranslationInterface:translate()
   */
  public static function t($string, array $args = [], array $options = []) {
    return \Drupal::translation()->translate($string, $args, $options);
  }

  /**
   * Set message function wrapper.
   *
   * @see \Drupal\Core\Messenger\MessengerInterface
   */
  public static function message($message = NULL, $type = 'status', $repeat = TRUE) {
    \Drupal::messenger()->addMessage($message, $type, $repeat);
  }

  /**
   * Gets the list of entities to process.
   *
   * Used in "all results" batch operation.
   *
   * @param array $data
   *   Processed view data.
   * @param array $context
   *   Batch context.
   */
  public static function getList(array $data, array &$context) {
    // Initialize batch.
    if (empty($context['sandbox'])) {
      $context['sandbox']['processed'] = 0;
      $context['sandbox']['page'] = 0;
      $context['sandbox']['total'] = $data['exclude_mode'] ? $data['total_results'] - count($data['exclude_list']) : $data['total_results'];
      $context['sandbox']['npages'] = ceil($data['total_results'] / $data['batch_size']);
      $context['results'] = $data;
    }

    $actionProcessor = \Drupal::service('views_bulk_operations.processor');
    $actionProcessor->initialize($data);

    // Populate queue.
    $list = $actionProcessor->getPageList($context['sandbox']['page']);
    $count = count($list);

    foreach ($list as $item) {
      $context['results']['list'][] = $item;
    }

    $context['sandbox']['page']++;
    $context['sandbox']['processed'] += $count;

    if ($context['sandbox']['page'] <= $context['sandbox']['npages']) {
      $context['finished'] = 0;
      $context['finished'] = $context['sandbox']['processed'] / $context['sandbox']['total'];
      $context['message'] = static::t('Prepared @count of @total entities for processing.', [
        '@count' => $context['sandbox']['processed'],
        '@total' => $context['sandbox']['total'],
      ]);
    }

  }

  /**
   * Save generated list to user tempstore.
   *
   * @param bool $success
   *   Was the process successful?
   * @param array $results
   *   Batch process results array.
   * @param array $operations
   *   Performed operations array.
   */
  public static function saveList($success, array $results, array $operations) {
    if ($success) {
      $results['redirect_url'] = $results['redirect_after_processing'];
      unset($results['redirect_after_processing']);
      $tempstore_factory = \Drupal::service('tempstore.private');
      $current_user = \Drupal::service('current_user');
      $tempstore_name = 'views_bulk_operations_' . $results['view_id'] . '_' . $results['display_id'];
      $results['prepopulated'] = TRUE;
      $tempstore_factory->get($tempstore_name)->set($current_user->id(), $results);
    }
  }

  /**
   * Batch operation callback.
   *
   * @param array $data
   *   Processed view data.
   * @param array $context
   *   Batch context.
   */
  public static function operation(array $data, array &$context) {
    // Initialize batch.
    if (empty($context['sandbox'])) {
      $context['sandbox']['processed'] = 0;
      $context['results']['operations'] = [];
      $context['sandbox']['page'] = 0;
      $context['sandbox']['npages'] = ceil($data['total_results'] / $data['batch_size']);
    }

    // Get entities to process.
    $actionProcessor = \Drupal::service('views_bulk_operations.processor');
    $actionProcessor->initialize($data);

    // Do the processing.
    $count = $actionProcessor->populateQueue($data, $context);

    $batch_results = $actionProcessor->process();
    if (!empty($batch_results)) {
      // Convert translatable markup to strings in order to allow
      // correct operation of array_count_values function.
      foreach ($batch_results as $result) {
        $context['results']['operations'][] = (string) $result;
      }
    }
    $context['sandbox']['processed'] += $count;
    $context['sandbox']['page']++;

    if ($context['sandbox']['page'] <= $context['sandbox']['npages']) {
      $context['finished'] = 0;

      $context['finished'] = $context['sandbox']['processed'] / $context['sandbox']['total'];
      $context['message'] = static::t('Processed @count of @total entities.', [
        '@count' => $context['sandbox']['processed'],
        '@total' => $context['sandbox']['total'],
      ]);
    }
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   Was the process successful?
   * @param array $results
   *   Batch process results array.
   * @param array $operations
   *   Performed operations array.
   */
  public static function finished($success, array $results, array $operations) {
    if ($success) {
      $operations = array_count_values($results['operations']);
      $details = [];
      foreach ($operations as $op => $count) {
        $details[] = $op . ' (' . $count . ')';
      }
      $message = static::t('Action processing results: @operations.', [
        '@operations' => implode(', ', $details),
      ]);
      static::message($message);
      if (isset($results['redirect_url'])) {
        return new RedirectResponse($results['redirect_url']->setAbsolute()->toString());
      }
    }
    else {
      $message = static::t('Finished with an error.');
      static::message($message, 'error');
    }
  }

  /**
   * Batch builder function.
   *
   * @param array $view_data
   *   Processed view data.
   */
  public static function getBatch(array &$view_data) {
    $current_class = get_called_class();

    // Prepopulate results.
    if (empty($view_data['list'])) {
      // Redirect this batch to the processing URL and set
      // previous redirect under a different key for later use.
      $view_data['redirect_after_processing'] = $view_data['redirect_url'];
      $view_data['redirect_url'] = Url::fromRoute('views_bulk_operations.execute_batch', [
        'view_id' => $view_data['view_id'],
        'display_id' => $view_data['display_id'],
      ]);

      $batch = [
        'title' => static::t('Prepopulating entity list for processing.'),
        'operations' => [
          [
            [$current_class, 'getList'],
            [$view_data],
          ],
        ],
        'progress_message' => static::t('Prepopulating, estimated time left: @estimate, elapsed: @elapsed.'),
        'finished' => [$current_class, 'saveList'],
      ];
    }

    // Execute action.
    else {
      $batch = [
        'title' => static::t('Performing @operation on selected entities.', ['@operation' => $view_data['action_label']]),
        'operations' => [
          [
            [$current_class, 'operation'],
            [$view_data],
          ],
        ],
        'progress_message' => static::t('Processing, estimated time left: @estimate, elapsed: @elapsed.'),
        'finished' => [$current_class, 'finished'],
      ];
    }

    return $batch;
  }

}
