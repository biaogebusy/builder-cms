<?php

namespace Drupal\blazy;

use Drupal\Core\Field\FormatterInterface;
use Drupal\blazy\Hook\BlazyHooks;
use Drupal\blazy\Hook\EditorHooks;
use Drupal\blazy\Hook\FieldHooks;
use Drupal\blazy\Hook\LibraryHooks;
use Drupal\blazy\Hook\ViewsHooks;
use Drupal\blazy\Internals\Internals;
use Drupal\editor\Entity\Editor;

/**
 * Provides hook_alter() methods for Blazy.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module. Please use the public method instead.
 *
 * @todo delete this file when min D11 after ::configSchemaInfoAlter().
 */
class BlazyAlter {

  /**
   * Implements hook_config_schema_info_alter().
   *
   * @todo delete this file after sub-module re-checks at D11.
   */
  public static function configSchemaInfoAlter(
    array &$definitions,
    $formatter = 'blazy_base',
    array $settings = [],
  ): void {
    Internals::configSchemaInfoAlter($definitions, 'blazy_base', $settings);
  }

  /**
   * Implements hook_library_info_alter().
   */
  public static function libraryInfoAlter(&$libraries, $extension): void {
    LibraryHooks::libraryInfoAlter($libraries, $extension);
  }

  /**
   * Implements hook_library_info_build().
   */
  public static function libraryInfoBuild() {
    return LibraryHooks::libraryInfoBuild();
  }

  /**
   * Implements hook_ckeditor_css_alter().
   */
  public static function ckeditorCssAlter(array &$css, Editor $editor): void {
    EditorHooks::ckeditorCssAlter($css, $editor);
  }

  /**
   * Provides the third party formatters where full blown Blazy is not worthy.
   */
  public static function thirdPartyFormatters(): array {
    return FieldHooks::thirdPartyFormatters();
  }

  /**
   * Implements hook_field_formatter_third_party_settings_form().
   */
  public static function fieldFormatterThirdPartySettingsForm(FormatterInterface $plugin): array {
    return FieldHooks::fieldFormatterThirdPartySettingsForm($plugin);
  }

  /**
   * Implements hook_field_formatter_settings_summary_alter().
   */
  public static function fieldFormatterSettingsSummaryAlter(array &$summary, $context): void {
    FieldHooks::fieldFormatterSettingsSummaryAlter($summary, $context);
  }

  /**
   * Implements hook_blazy_settings_alter().
   */
  public static function blazySettingsAlter(array &$build, $object): void {
    BlazyHooks::blazySettingsAlter($build, $object);
  }

  /**
   * Implements hook_views_data_alter().
   */
  public static function viewsDataAlter(&$data): void {
    ViewsHooks::viewsDataAlter($data);
  }

  /**
   * Implements hook_views_plugins_style_alter().
   */
  public static function viewsPluginsStyleAlter(array &$plugins): void {
    ViewsHooks::viewsPluginsStyleAlter($plugins);
  }

}
