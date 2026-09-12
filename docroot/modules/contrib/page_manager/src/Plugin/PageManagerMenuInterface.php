<?php

namespace Drupal\page_manager\Plugin;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Interface for Page Manager menu type plugins.
 */
interface PageManagerMenuInterface extends PluginFormInterface, ConfigurableInterface, PluginInspectionInterface {

  /**
   * Invalidates the menu item.
   */
  public function invalidate();

}
