<?php

namespace Drupal\elasticsearch_connector\Event;

use Drupal\search_api\IndexInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event fired after an index is created.
 */
class IndexCreatedEvent extends Event {

  /**
   * Constructor for IndexCreatedEvent.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index that was created.
   */
  public function __construct(private IndexInterface $index) {}

  /**
   * Get the index.
   */
  public function getIndex(): IndexInterface {
    return $this->index;
  }

}
