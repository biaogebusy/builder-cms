<?php

namespace Drupal\redis\Flood;

/**
 * Defines the database flood backend. This is the default Drupal backend.
 */
class Relay extends PhpRedis {

  /**
   * @var \Relay\Relay
   */
  protected $client;

}
