<?php

namespace Drupal\advancedqueue\Plugin\views\argument_validator;

use Drupal\advancedqueue\Entity\Queue;
use Drupal\views\Plugin\views\argument_validator\ArgumentValidatorPluginBase;

/**
 * Defines an argument validator plugin for queue backends.
 *
 * @ViewsArgumentValidator(
 *   id = "advancedqueue_backend",
 *   title = @Translation("Queue backend"),
 * )
 */
class QueueBackend extends ArgumentValidatorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function validateArgument($arg) {
    $queue = Queue::load($arg);
    return $queue && $queue->getBackendId() === 'database';
  }

}
