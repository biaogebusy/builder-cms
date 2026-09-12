<?php

namespace Drupal\blazy\Hook;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Field\FormatterInterface;
use Drupal\blazy\Internals\Internals;

/**
 * Hook implementations for field.
 *
 * @todo add #[Hook] attribute, and change them to instance class for D11.
 */
class FieldHooks {

  /**
   * Implements hook_field_formatter_third_party_settings_form().
   *
   * {@inheritdoc}
   */
  public static function fieldFormatterThirdPartySettingsForm(FormatterInterface $plugin): array {
    if (in_array($plugin->getPluginId(), self::thirdPartyFormatters())) {
      return [
        'blazy' => [
          '#type' => 'checkbox',
          '#title' => 'Blazy',
          '#default_value' => $plugin->getThirdPartySetting('blazy', 'blazy', FALSE),
        ],
      ];
    }
    return [];
  }

  /**
   * Implements hook_field_formatter_settings_summary_alter().
   *
   * {@inheritdoc}
   */
  public static function fieldFormatterSettingsSummaryAlter(array &$summary, $context): void {
    if ($formatter = $context['formatter'] ?? NULL) {
      $on = $formatter->getThirdPartySetting('blazy', 'blazy', FALSE);
      if ($on && in_array($formatter->getPluginId(), self::thirdPartyFormatters())) {
        $summary[] = 'Blazy';
      }

      // Provide removal message, applicable to all Blazy ecosystem.
      $plugin_id = $formatter->getPluginId();
      if (strpos($plugin_id, '_file') !== FALSE) {
        $config = $formatter->getSettings();
        // All blazy file ecosystem has this unique option.
        if (isset($config['svg_hide_caption'])) {
          $definition = $context['field_definition'];
          $settings   = $definition->getSettings();
          $extensions = $settings['file_extensions'] ?? '';
          $plugin     = $formatter->getPluginDefinition();

          if (!Internals::has($extensions, 'svg') && $definition->getType() == 'image') {
            $summary[] = t('<h5>No SVG file extensions, use @provider Image instead.</h5>', [
              '@provider' => Unicode::ucfirst($plugin['provider']),
            ]);
          }
        }
      }
    }
  }

  /**
   * Provides the third party formatters where full blown Blazy is not worthy.
   *
   * @todo make it private after another sub-module check.
   */
  public static function thirdPartyFormatters(): array {
    $formatters = ['file_audio', 'file_video'];
    if ($manager = Internals::blazy()) {
      $formatters = $manager->thirdPartyFormatters();
    }
    return array_unique($formatters);
  }

}
