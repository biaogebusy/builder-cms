<?php

namespace Drupal\views_add_button;

/**
 * An interface for all ViewsAddButton type plugins.
 */
interface ViewsAddButtonInterface {

  /**
   * Provide a description of the plugin.
   *
   * @return string
   *   A string description of the plugin.
   */
  public function description();

}
