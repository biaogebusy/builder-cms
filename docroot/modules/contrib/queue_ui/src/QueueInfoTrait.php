<?php

namespace Drupal\queue_ui;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides methods to retrieve information from a queue definition.
 */
trait QueueInfoTrait {

  use StringTranslationTrait;

  /**
   * Retrieves the full title of a queue, optionally including its label.
   *
   * @param array $queue_definition
   *   The definition of the queue, containing necessary details.
   *
   * @return string
   *   The full title of the queue, including its label if available.
   */
  public function getQueueFullTitle(array $queue_definition): string {
    $label = $this->getQueueLabel($queue_definition);
    return $label ?
      $this->t('@queue_title: @queue_label', [
        '@queue_title' => $this->getQueueTitle($queue_definition),
        '@queue_label' => $label,
      ]) :
      $this->getQueueTitle($queue_definition);
  }

  /**
   * Retrieves the title of a queue.
   *
   * @param array $queue_definition
   *   The definition of the queue, containing necessary details.
   *
   * @return string
   *   The title of the queue.
   */
  public function getQueueTitle(array $queue_definition): string {
    return (string) $queue_definition['title'];
  }

  /**
   * Retrieves the label of a queue from its definition.
   *
   * @param array $queue_definition
   *   The definition of the queue, containing necessary details.
   *
   * @return string
   *   The label of the queue, or an empty string if not available.
   */
  public function getQueueLabel(array $queue_definition): string {
    return (string) ($queue_definition['admin_label'] ?? '');
  }

}
