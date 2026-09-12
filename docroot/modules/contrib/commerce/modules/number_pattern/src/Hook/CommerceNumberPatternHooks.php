<?php

namespace Drupal\commerce_number_pattern\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for Commerce Number Pattern.
 */
class CommerceNumberPatternHooks {

  /**
   * Constructs a new CommerceNumberPatternHooks object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    protected readonly ModuleHandlerInterface $moduleHandler,
  ) {
  }

  /**
   * Implements hook_menu_links_discovered_alter().
   */
  #[Hook('menu_links_discovered_alter')]
  public function menuLinksDiscoveredAlter(&$links): void {
    // Move the number pattern page to the Order configuration group.
    if ($this->moduleHandler->moduleExists('commerce_order')) {
      $links['entity.commerce_number_pattern.collection']['parent'] = 'commerce_order.configuration';
    }
  }

}
