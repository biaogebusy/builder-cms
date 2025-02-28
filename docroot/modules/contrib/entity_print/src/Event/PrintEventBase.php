<?php

namespace Drupal\entity_print\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Drupal\entity_print\Plugin\PrintEngineInterface;

/**
 * Event base class.
 */
abstract class PrintEventBase extends Event {

  /**
   * The print engine plugin.
   *
   * @var \Drupal\entity_print\Plugin\PrintEngineInterface
   */
  protected $printEngine;

  /**
   * The Print Engine event base class.
   *
   * @param \Drupal\entity_print\Plugin\PrintEngineInterface $print_engine
   *   The Print Engine.
   */
  public function __construct(PrintEngineInterface $print_engine) {
    $this->printEngine = $print_engine;
  }

  /**
   * Gets the Print Engine plugin that will print the Print.
   *
   * @return \Drupal\entity_print\Plugin\PrintEngineInterface
   *   The Print Engine.
   */
  public function getPrintEngine() {
    return $this->printEngine;
  }

}
