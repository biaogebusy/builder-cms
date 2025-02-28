<?php

namespace Drupal\bootstrap_layout_builder;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Defines an interface for blb_layout entity storage classes.
 */
interface LayoutInterface extends ConfigEntityInterface {

  /**
   * Returns all the options from a layout options sorted correctly.
   *
   * @return \Drupal\bootstrap_layout_builder\LayoutOptionInterface[]
   *   An array of layout options entities.
   */
  public function getLayoutOptions();

  /**
   * Returns the number of columns at the layout.
   */
  public function getNumberOfColumns();

}
