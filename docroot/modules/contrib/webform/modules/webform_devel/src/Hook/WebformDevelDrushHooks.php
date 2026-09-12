<?php

namespace Drupal\webform_devel\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform_devel.
 */
class WebformDevelDrushHooks {

  /**
   * Implements hook_drush_command().
   */
  #[Hook('drush_command')]
  public function drushCommand() {
    $items = [];
    $items['webform-devel-reset'] = [
      'description' => 'Resets Webform user data and saved state for messages',
      'core' => [
        '8+',
      ],
      'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_ROOT,
      'examples' => [
        'webform-devel-reset' => 'Resets Webform user data and saved state for messages',
      ],
      'aliases' => [
        'wfdr',
        'webform:devel:reset',
      ],
    ];
    return $items;
  }

  /**
   * Implements hook_drush_help().
   */
  #[Hook('drush_help')]
  public function drushHelp($section) {
    switch ($section) {
      case 'drush:webform-reset':
        return dt('Resets Webform user data and saved state for messages');

      case 'meta:webform:title':
        return dt('Webform development commands');

      case 'meta:webform:summary':
        return dt('Developer specific commands for the Webform module.');
    }
  }

}
