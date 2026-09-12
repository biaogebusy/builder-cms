<?php

namespace Drupal\blazy_layout\Hook;

/**
 * Hook implementations for theme.
 *
 * @todo add #[Hook] attribute, and change them to instance class for D11.
 */
class HelpHooks {

  /**
   * Implements hook_help().
   */
  public static function help($route_name) {
    if ($route_name == 'help.page.blazy_layout') {
      $output = file_get_contents(dirname(dirname(dirname(__FILE__))) . '/README.md');
      return \blazy()->markdown($output);
    }
    return '';
  }

}
