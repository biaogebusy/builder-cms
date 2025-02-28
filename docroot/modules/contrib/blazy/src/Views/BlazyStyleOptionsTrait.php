<?php

namespace Drupal\blazy\Views;

use Drupal\Component\Utility\Html;
use Drupal\views\Views;

@trigger_error('The ' . __NAMESPACE__ . '\BlazyStyleOptionsTrait is deprecated in blazy:8.x-2.17 and is removed from blazy:8.x-3.0. Use \Drupal\blazy\Views\BlazyStylePluginBase instead. See https://www.drupal.org/node/3367304', E_USER_DEPRECATED);

/**
 * A Trait common for optional views style plugins.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module. Please extend base classes intead.
 *
 * @deprecated in blazy:8.x-2.17 and is removed from blazy:8.x-3.0. Use
 *   Drupal\blazy\Views\BlazyStylePluginBase instead.
 * @see https://www.drupal.org/node/3367304
 */
trait BlazyStyleOptionsTrait {

  /**
   * The Views as options.
   *
   * @var array
   */
  protected $viewsOptions;

  /**
   * Returns available fields for select options.
   */
  protected function getDefinedFieldOptions(array $defined_options = []): array {
    $field_names = $this->displayHandler->getFieldLabels();
    $definition = [];
    $stages = [
      'blazy_media',
      'block_field',
      'colorbox',
      'entity_reference_entity_view',
      'gridstack_file',
      'gridstack_media',
      'photobox',
      'video_embed_field_video',
      'youtube_video',
    ];

    // Formatter based fields.
    $options = [];
    foreach ($this->displayHandler->getOption('fields') as $field => $handler) {
      // This is formatter based type, not actual field type.
      if ($formatter = ($handler['type'] ?? NULL)) {
        switch ($formatter) {
          // @todo recheck other reasonable image-related formatters.
          case 'blazy':
          case 'image':
          case 'media':
          case 'media_thumbnail':
          case 'intense':
          case 'responsive_image':
          case 'svg_image_field_formatter':
          case 'video_embed_field_thumbnail':
          case 'video_embed_field_colorbox':
          case 'youtube_thumbnail':
            $options['images'][$field] = $field_names[$field];
            $options['overlays'][$field] = $field_names[$field];
            $options['thumbnails'][$field] = $field_names[$field];
            break;

          case 'list_key':
            $options['layouts'][$field] = $field_names[$field];
            break;

          case 'entity_reference_label':
          case 'text':
          case 'string':
          case 'link':
            $options['links'][$field] = $field_names[$field];
            $options['titles'][$field] = $field_names[$field];
            if ($formatter != 'link') {
              $options['thumb_captions'][$field] = $field_names[$field];
            }
            break;
        }

        $classes = ['list_key', 'entity_reference_label', 'text', 'string'];
        if (in_array($formatter, $classes)) {
          $options['classes'][$field] = $field_names[$field];
        }

        // Allows nested sliders.
        $sliders = strpos($formatter, 'slick') !== FALSE
          || strpos($formatter, 'splide') !== FALSE;
        if ($sliders || in_array($formatter, $stages)) {
          $options['overlays'][$field] = $field_names[$field];
        }

        // Allows advanced formatters/video as the main image replacement.
        // They are not reasonable for thumbnails, but main images.
        // Note: Certain Responsive image has no ID at Views, possibly a bug.
        if (in_array($formatter, $stages)) {
          $options['images'][$field] = $field_names[$field];
        }
      }

      // Content: title is not really a field, unless title.module installed.
      if (isset($handler['field'])) {
        if ($handler['field'] == 'title') {
          $options['classes'][$field] = $field_names[$field];
          $options['titles'][$field] = $field_names[$field];
          $options['thumb_captions'][$field] = $field_names[$field];
        }

        if ($handler['field'] == 'rendered_entity') {
          $options['images'][$field] = $field_names[$field];
          $options['overlays'][$field] = $field_names[$field];
        }

        if (in_array($handler['field'], ['nid', 'nothing', 'view_node'])) {
          $options['links'][$field] = $field_names[$field];
          $options['titles'][$field] = $field_names[$field];
        }

        if (in_array($handler['field'], ['created'])) {
          $options['classes'][$field] = $field_names[$field];
        }

        $blazies = strpos($handler['field'], 'blazy_') !== FALSE;
        if ($blazies) {
          $options['images'][$field] = $field_names[$field];
          $options['overlays'][$field] = $field_names[$field];
          $options['thumbnails'][$field] = $field_names[$field];
        }
      }

      // Captions can be anything to get custom works going.
      $options['captions'][$field] = $field_names[$field];
    }

    $definition['plugin_id'] = $this->getPluginId();
    $definition['settings'] = $this->options;
    $definition['_views'] = TRUE;

    // Provides the requested fields based on available $options.
    foreach ($defined_options as $key) {
      $definition[$key] = $options[$key] ?? [];
    }

    $contexts = [
      'handler' => $this->displayHandler,
      'view' => $this->view,
    ];
    $this->manager->moduleHandler()->alter('blazy_views_field_options', $definition, $contexts);

    return $definition;
  }

  /**
   * Returns an array of views for option list.
   *
   * Cannot use Views::getViewsAsOptions() as we need to limit to something.
   */
  protected function getViewsAsOptions($plugin = 'html_list'): array {
    if (!isset($this->viewsOptions[$plugin])) {
      $options = [];

      // Convert list of objects to options for the form.
      foreach (Views::getEnabledViews() as $name => $view) {
        foreach ($view->get('display') as $id => $display) {
          $valid = ($display['display_options']['style']['type'] ?? NULL) == $plugin;
          if ($valid) {
            $label = $view->label() . ' (' . $display['display_title'] . ')';
            $options[$name . ':' . $id] = Html::escape($label);
          }
        }
      }
      $this->viewsOptions[$plugin] = $options;
    }
    return $this->viewsOptions[$plugin];
  }

}
