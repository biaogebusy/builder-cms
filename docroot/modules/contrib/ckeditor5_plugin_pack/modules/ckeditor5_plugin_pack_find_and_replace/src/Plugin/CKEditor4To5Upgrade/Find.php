<?php

namespace Drupal\ckeditor5_plugin_pack_find_and_replace\Plugin\CKEditor4To5Upgrade;

use Drupal\ckeditor5\HTMLRestrictions;
use Drupal\ckeditor5\Plugin\CKEditor4To5UpgradePluginInterface;
use Drupal\filter\FilterFormatInterface;
use Drupal\Core\Plugin\PluginBase;

/**
 * Provides a CKEditor4 to CKEditor5 upgrade path for the Find button.
 *
 * @CKEditor4To5Upgrade(
 *   id = "find",
 *   cke4_buttons = {
 *     "Find",
 *     "Find RTL",
 *     "Replace",
 *   },
 *   cke4_plugin_settings = {
 *   },
 *   cke5_plugin_elements_subset_configuration = {
 *   }
 * )
 */
class Find extends PluginBase implements CKEditor4To5UpgradePluginInterface {

// phpcs:disable Drupal.NamingConventions.ValidFunctionNameSniff --inherited

  /**
   * {@inheritdoc}
   */
  public function mapCKEditor4ToolbarButtonToCKEditor5ToolbarItem(string $cke4_button, HTMLRestrictions $text_format_html_restrictions): ?array {
    $map = [
      'Find' => 'findAndReplace',
      'Find RTL' => 'findAndReplace',
      'Replace' => 'findAndReplace',
    ];
    if (array_key_exists($cke4_button, $map)) {
      return [$map[$cke4_button]];
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function mapCKEditor4SettingsToCKEditor5Configuration(string $cke4_plugin_id, array $cke4_plugin_settings): ?array {
    throw new \OutOfBoundsException();
  }

  /**
   * {@inheritdoc}
   */
  public function computeCKEditor5PluginSubsetConfiguration(string $cke5_plugin_id, FilterFormatInterface $text_format): ?array {
    throw new \OutOfBoundsException();
  }

}
