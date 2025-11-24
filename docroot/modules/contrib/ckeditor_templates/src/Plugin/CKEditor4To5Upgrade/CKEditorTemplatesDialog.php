<?php

namespace Drupal\ckeditor_templates\Plugin\CKEditor4To5Upgrade;

use Drupal\ckeditor5\HTMLRestrictions;
use Drupal\ckeditor5\Plugin\CKEditor4To5UpgradePluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\filter\FilterFormatInterface;

/**
 * Provides the CKEditor 4 to 5 upgrade for CKEditor templates plugin.
 *
 * @CKEditor4To5Upgrade(
 *   id = "templates",
 *   cke4_plugin_settings = {
 *     "templates",
 *   },
 *   cke4_buttons = {
 *     "Templates"
 *   }
 * )
 */
class CKEditorTemplatesDialog extends PluginBase implements CKEditor4To5UpgradePluginInterface {

  /**
   * @inheritDoc
   */
  public function mapCKEditor4ToolbarButtonToCKEditor5ToolbarItem(string $cke4_button, HTMLRestrictions $text_format_html_restrictions): ?array {
    switch ($cke4_button) {
      case 'Templates':
        return ['ckeditorTemplates'];

      default:
        throw new \OutOfBoundsException();
    }
  }

  /**
   * @inheritDoc
   */
  public function mapCKEditor4SettingsToCKEditor5Configuration(string $cke4_plugin_id, array $cke4_plugin_settings): ?array {
    switch ($cke4_plugin_id) {
      case 'templates':
        $sanitized = [];

        $sanitized['replace_content'] = isset($cke4_plugin_settings['replace_content']) ? ($cke4_plugin_settings['replace_content'] ? TRUE : FALSE) : FALSE;

        return ['ckeditor_templates_plugin' => $sanitized];
        break;

      default:
        throw new \OutOfBoundsException();
    }
  }

  /**
   * @inheritDoc
   */
  public function computeCKEditor5PluginSubsetConfiguration(string $cke5_plugin_id, FilterFormatInterface $text_format): ?array {
    throw new \OutOfBoundsException();
  }

}
