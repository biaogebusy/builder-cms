<?php

namespace Drupal\blazy\Theme;

use Drupal\blazy\Blazy;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\internals\Internals;
use Drupal\blazy\Utility\Arrays;

/**
 * Provides optional Views integration.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 */
class BlazyViews {

  /**
   * Implements hook_views_pre_render().
   */
  public static function viewsPreRender($view): void {
    $loads = [];
    $ajax  = $view->ajaxEnabled();

    // At least, less aggressive than sitewide hook_library_info_alter().
    // @todo remove when VIS alike added `Drupal.detachBehaviors()` to their JS.
    if ($ajax) {
      $loads['library'][] = 'blazy/bio.ajax';
    }

    // Load Blazy library once, not per field, if any Blazy Views field found.
    if ($blazy = self::viewsField($view)) {
      $manager   = $blazy->blazyManager();
      $plugin_id = $view->getStyle()->getPluginId();
      $settings  = $blazy->mergedViewsSettings();
      $blazies   = $settings['blazies'];

      $blazies->set('unlazy', FALSE);

      $load  = $manager->attach($settings);
      $loads = $manager->merge($load, $loads);
      $grid  = $plugin_id == 'blazy';

      if ($options = $view->getStyle()->options) {
        $grid = empty($options['grid']) ? $grid : TRUE;
      }

      // Prevents dup [data-LIGHTBOX-gallery] if the Views style supports Grid.
      if (!$grid) {
        $view->element['#attributes'] = $view->element['#attributes'] ?? [];
        Attributes::container($view->element['#attributes'], $settings);
      }
    }

    if ($loads) {
      $view->element['#attached'] = Arrays::merge($loads, $view->element, '#attached');
    }
  }

  /**
   * Returns one of the Blazy Views fields, if available.
   */
  public static function viewsField($view) {
    foreach (['file', 'media'] as $entity) {
      if (isset($view->field['blazy_' . $entity])) {
        return $view->field['blazy_' . $entity];
      }
    }
    return NULL;
  }

  /**
   * Implements hook_preprocess_views_view().
   */
  public static function preprocessViewsView(array &$variables, $lightboxes): void {
    preg_match('~blazy--(.*?)-gallery~', $variables['css_class'], $matches);
    $lightbox = $matches[1] ? str_replace('-', '_', $matches[1]) : FALSE;

    // Given blazy--photoswipe-gallery, adds the [data-photoswipe-gallery], etc.
    if ($lightbox && in_array($lightbox, $lightboxes)) {
      $variables['attributes'] = $variables['attributes'] ?? [];

      $data = [
        'namespace' => 'blazy',
        'media_switch' => $lightbox,
      ];

      $settings = Blazy::init($data);

      $blazies = $settings['blazies'];
      if ($view = $variables['view']) {
        $count = count($view->result);
        $blazies->set('count', $count)
          ->set('total', $count);
      }

      \blazy()->moduleHandler()->alter('blazy_is_view', $settings, $variables);

      Attributes::container($variables['attributes'], $settings);
    }
  }

  /**
   * Provides common views-related settings.
   */
  public static function settings($view, array $settings, array $data = []): array {
    $count     = count($view->result);
    $view_name = $view->storage->id();
    $view_mode = $view->current_display;
    $style     = $view->style_plugin;
    $embedded  = $data['embedded'] ?? FALSE;
    $extras    = $data['extras'] ?? [];
    $is_field  = $data['is_field'] ?? FALSE;
    $is_view   = $data['is_view'] ?? FALSE;
    $plugin_id = $data['plugin_id'] ?? NULL;
    // $plugin_id  = is_null($style) ? 'xs' : $style->getPluginId();
    $display   = is_null($style) ? 'xd' : $style->displayHandler->getPluginId();
    $instance  = "{$view_name}-{$display}-{$view_mode}";
    $which     = $is_field ? 'views-field' : 'views';
    $id        = "{$which}-{$instance}";
    $id        = $plugin_id . '--' . substr(md5($id), 0, 11);
    $id        = str_replace('_', '-', $id);
    $id        = Internals::getHtmlId($id);
    $settings += BlazyDefault::lazySettings();
    $blazies   = Internals::verify($settings);

    // Prepare needed settings to work with.
    // @todo convert some to blazies, and remove these after sub-modules.
    $settings['id']           = $id;
    $settings['count']        = $count;
    $settings['instance_id']  = $instance;
    $settings['multiple']     = $count > 1;
    $settings['plugin_id']    = $settings['view_plugin_id'] = $plugin_id;
    $settings['view_name']    = $view_name;
    $settings['view_display'] = $display;

    $data = Internals::getViewFieldData($view);
    $view_info = [
      'count'       => $count,
      'display'     => $display,
      'embedded'    => $embedded,
      'instance_id' => $instance,
      'data'        => $data,
      'multifield'  => count($data['fields']) > 1,
      'name'        => $view_name,
      'plugin_id'   => $plugin_id,
      'view_mode'   => $view_mode,
    ] + $extras;

    $blazies->set('cache.metadata.keys', [$id, $view_mode, $count], TRUE)
      ->set('cache.metadata.tags', $view->getCacheTags() ?: [], TRUE)
      ->set('count', $count)
      ->set('total', $count)
      ->set('css.id', $id)
      ->set('is.multiple', $count > 1)
      ->set('is.view', $is_view)
      // Prevents potential broken core image formatter due to lack of options.
      ->set('libs.ratio', TRUE)
      ->set('use.ajax', $view->ajaxEnabled())
      ->set('view', $view_info, TRUE);

    return $settings;
  }

}
