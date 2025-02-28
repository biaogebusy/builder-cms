<?php

namespace Drupal\panels;

use Symfony\Contracts\EventDispatcher\Event;
use Drupal\panels\Plugin\DisplayVariant\PanelsDisplayVariant;

/**
 * Event object for events relating to Panels display variants.
 */
class PanelsVariantEvent extends Event {

  /**
   * The Panels display variant.
   *
   * @var \Drupal\panels\Plugin\DisplayVariant\PanelsDisplayVariant
   */
  protected $variant;

  /**
   * PanelsVariantEvent constructor.
   *
   * @param \Drupal\panels\Plugin\DisplayVariant\PanelsDisplayVariant $variant
   *   The Panels display variant.
   */
  public function __construct(PanelsDisplayVariant $variant) {
    $this->variant = $variant;
  }

  /**
   * Returns the Panels display variant that triggered the event.
   *
   * @return \Drupal\panels\Plugin\DisplayVariant\PanelsDisplayVariant
   *   The Panels display variant.
   */
  public function getVariant() {
    return $this->variant;
  }

}
