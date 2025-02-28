<?php

namespace Drupal\Tests\entity_print\Kernel;

use Drupal\entity_print\Event\PrintHtmlAlterEvent;
use Drupal\entity_print\PrintEngineException;

/**
 * A test event.
 */
class PrintHtmlAlterTestEvent extends PrintHtmlAlterEvent {

  /**
   * PrintHtmlAlterTestEvent constructor.
   */
  public function __construct() {

  }

  /**
   * Throws an exception so we can test PostRenderSubscriber.
   */
  public function &getHtml() {
    throw new PrintEngineException('getHtml should never be called');
  }

  /**
   * Method to change to saved PHP SAPI string.
   */
  public function setPhpSapi(string $sapi) {
    $this->phpSapi = $sapi;
  }

}
