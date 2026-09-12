<?php

namespace Drupal\blazy\Hook;

use Drupal\blazy\Internals\Internals;

/**
 * Hook implementations for blazy.
 *
 * @todo add #[Hook] attribute, and change them to instance class for D11.
 */
class BlazyHooks {

  /**
   * Implements hook_blazy_settings_alter().
   *
   * Provides minimal flags for Blazy field formatters embedded inside a view.
   * With this limited info, sub-modules like Splidebox can correctly inject
   * its options via [data-splidebox] to the correct container, etc., and avoid
   * duplicating injections at both embedded Blazy formatter and Blazy Grid view
   * style. And the same principle applies to all sub-modules.
   *
   * Warning! Do not alter configurable settings like use_theme_field here, it
   * caused 2.16 chaotic markups with Views embedded blazy formatters.
   *
   * {@inheritdoc}
   */
  public static function blazySettingsAlter(array &$build, $object): void {
    /** @var array $settings */
    $settings = &$build['#settings'];
    $blazies = Internals::getBlazies($settings);

    // Adds bio.ajax to fix product variation AJAX within BigPipe.
    // Views AJAX will automatically work, however to support other non-views
    // AJAX, add more conditions to your custom hook_blazy_settings_alter.
    if ($type = $blazies->get('field.entity_type')) {
      if ($type == 'commerce_product_variation') {
        $blazies->set('use.ajax', TRUE);
      }
    }

    // Sniffs for Views to allow block__no_wrapper, views_no_wrapper, etc.
    $function = 'views_get_current_view';
    // @todo phpstan bug, misleading with nullable function return.
    /* @phpstan-ignore-next-line */
    if (is_callable($function) && $view = $function()) {
      $name      = $view->storage->id();
      $view_mode = $view->current_display;
      $style     = $view->style_plugin;
      $display   = $style ? $style->displayHandler->getPluginId() : '';
      $plugin_id = $style ? $style->getPluginId() : '';

      // Only eat what we can chew:
      $data = Internals::getViewFieldData($view);
      $current = [
        'count'       => count($view->result),
        'display'     => $display,
        'embedded'    => TRUE,
        'instance_id' => str_replace('_', '-', "{$name}-{$display}-{$view_mode}"),
        'data'        => $data,
        'multifield'  => count($data['fields']) > 1,
        'name'        => $name,
        'plugin_id'   => $plugin_id,
        'view_mode'   => $view_mode,
      ];

      // Collects view info for the embedded Blazy, and this is not a view.
      $blazies->set('view', $current, TRUE)
        ->set('is.view', FALSE);
    }
  }

}
