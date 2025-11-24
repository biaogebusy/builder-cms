<?php

namespace Drupal\admin_toolbar\Render\Element;

use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Render element element for AdminToolbar.
 *
 * @package Drupal\admin_toolbar\Render\Element
 */
class AdminToolbar implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRenderTray'];
  }

  /**
   * Renders the toolbar's administration tray.
   *
   * This is a clone of core's toolbar_prerender_toolbar_administration_tray()
   * function, which uses setMaxDepth(4) instead of setTopLevelOnly().
   *
   * @param array<mixed> $build
   *   A renderable array.
   *
   * @return array<mixed>
   *   The updated renderable array.
   *
   * @see toolbar_prerender_toolbar_administration_tray()
   */
  public static function preRenderTray(array $build) {
    // Get the core toolbar menu tree service.
    /** @var \Drupal\toolbar\Menu\ToolbarMenuLinkTree $menu_tree */
    $menu_tree = \Drupal::service('toolbar.menu_tree');
    $parameters = new MenuTreeParameters();
    // Get the maximum depth of the menu and pass it as a menu tree parameter.
    $max_depth = \Drupal::config('admin_toolbar.settings')->get('menu_depth');
    $parameters->setRoot('system.admin')->excludeRoot()->setMaxDepth($max_depth)->onlyEnabledLinks();
    // Load the 'Administration' menu tree.
    $tree = $menu_tree->load('admin', $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
      // Add admin toolbar specific manipulator to add CSS classes and options.
      ['callable' => 'toolbar_tools_menu_navigation_links'],
    ];
    // Apply all the callback manipulators to the menu tree.
    $tree = $menu_tree->transform($tree, $manipulators);
    // Build and return a renderable array from the menu tree.
    $build['administration_menu'] = $menu_tree->build($tree);
    return $build;
  }

}
