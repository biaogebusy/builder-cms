<?php

namespace Drupal\blazy\Views;

use Drupal\blazy\Blazy;
use Drupal\blazy\internals\Internals;
use Drupal\blazy\Theme\BlazyViews;
use Drupal\blazy\Utility\Sanitize;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Render\Markup;

@trigger_error('The ' . __NAMESPACE__ . '\BlazyStyleBaseTrait is deprecated in blazy:8.x-2.14 and is removed from blazy:8.x-3.0. Use \Drupal\blazy\Views\BlazyStyleBase instead. See https://www.drupal.org/node/3367304', E_USER_DEPRECATED);

/**
 * A Trait common for optional views style plugins.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module. Please extend base classes intead.
 *
 * @deprecated in blazy:8.x-2.14 and is removed from blazy:8.x-3.0. Use
 *   \Drupal\blazy\Views\BlazyStyleBase instead.
 * @see https://www.drupal.org/node/3367304
 */
trait BlazyStyleBaseTrait {

  /**
   * The first Blazy formatter found to get data from for lightbox gallery, etc.
   *
   * @var array
   */
  protected $firstImage;

  /**
   * The dynamic html settings.
   *
   * @var array
   */
  protected $htmlSettings = [];

  /**
   * The blazy manager service.
   *
   * @var \Drupal\blazy\BlazyManagerInterface
   */
  protected $blazyManager;

  /**
   * {@inheritdoc}
   */
  public function blazyManager() {
    return $this->blazyManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldString($row, $name, $index, $clean = TRUE): array {
    $values = [];

    // Content title/List/Text, either as link or plain text.
    if ($value = $this->getFieldValue($index, $name)) {
      $value = is_array($value) ? array_filter($value) : $value;

      // Entity reference label where the above $value can be term ID.
      if ($markup = $this->getField($index, $name)) {
        $value = is_object($markup) ? trim(strip_tags($markup->__toString()) ?: '') : $value;
      }

      if (is_string($value)) {
        // Only respects tags with default CSV, just too much to worry about.
        if (strpos($value, ',') !== FALSE) {
          $tags = array_map('trim', explode(',', $value));
          $rendered_tags = [];
          foreach ($tags as $tag) {
            $tag = trim($tag ?: '');
            $rendered_tags[] = $clean ? Html::cleanCssIdentifier(mb_strtolower($tag)) : $tag;
          }
          // Meant to have space delimited taxonomy values.
          $clean = FALSE;
          $values[$index] = implode(' ', $rendered_tags);
        }
        else {
          $values[$index] = $value;
        }
      }
      else {
        // Normally link field values.
        if (is_array($value)) {
          if ($val = $value[0]['value'] ?? '') {
            $values[$index] = $val;
          }
        }
      }
    }

    return Sanitize::attribute($values, TRUE, $clean);
  }

  /**
   * Provides commons settings for the style plugins.
   */
  protected function buildSettings() {
    $view    = $this->view;
    $options = $this->options;

    $data = [
      'embedded'  => FALSE,
      'is_view'   => TRUE,
      'plugin_id' => $this->getPluginId(),
    ];

    // Prepare needed settings to work with.
    $settings = BlazyViews::settings($view, $options, $data);
    $blazies  = $settings['blazies'];
    $is_grid  = !empty($settings['style']) && !empty($settings['grid']);

    $settings['caption'] = empty($settings['caption'])
      ? [] : array_filter($settings['caption']);

    // Since 2.17, the item array was to replace all sub-modules theme_ITEM() by
    // theme_blazy() for easy improvements at 3.x. Not implemented at 2.x, yet.
    $blazies->set('namespace', static::$namespace)
      ->set('is.grid', $is_grid && $blazies->is('multiple'))
      ->set('item.id', static::$itemId)
      ->set('item.prefix', static::$itemPrefix)
      ->set('item.caption', static::$captionId);

    // Be sure to run after item setup.
    if (!method_exists($this->manager, 'verify')) {
      return $settings;
    }

    // @todo replace at 3.x with $this->manager->verifySafely($settings);
    $this->manager->verifySafely($settings);
    $this->manager->preSettings($settings);

    $this->prepareSettings($settings);

    // @todo remove, used by outlayer.
    if (!empty($this->htmlSettings)) {
      $settings = $this->manager->merge($this->htmlSettings, $settings);
    }

    $this->manager->postSettings($settings);

    $this->manager->moduleHandler()->alter('blazy_settings_views', $settings, $view);
    $this->manager->postSettingsAlter($settings);
    return $settings;
  }

  /**
   * Check Blazy formatter to build lightbox galleries.
   *
   * Make this view container aware of Blazy formatters, normally to inject
   * relevant lightbox info about which it is not aware of due to such info is
   * not provided at view style level, but field formatter one.
   */
  protected function checkBlazy(array &$settings, array $build, array $rows = []) {
    // Extracts Blazy formatter settings if available.
    // @todo re-check and remove, first.data already takes care of this.
    // The ::isBlazy() is still needed for Views fields, not just this view,
    // but not here, normally at modules' managers.
    // However if any issues, re-enable this check, and refine downstream more.
    // if (empty($settings['vanilla']) && isset($build['items'][0])) {
    // $this->manager()->isBlazy($settings, $build['items'][0]);
    // }
    $blazies = $settings['blazies'];
    if ($data = $this->getFirstImage($rows[0] ?? NULL)) {
      $blazies->set('first.data', $data);

      // @todo recheck $this->manager->preSettings($settings);
      if ($subsets = $this->manager->toHashtag($data)) {
        if ($blazy = $subsets['blazies']) {
          $field = $blazy->get('field', []);
          $field['count'] = $blazy->get('count');
          $blazies->set('view.formatter', $field);
        }
      }
    }
  }

  /**
   * Returns the first Blazy formatter found, to save image dimensions once.
   *
   * Given 100 images on a page, Blazy will call
   * ImageStyle::transformDimensions() once rather than 100 times and let the
   * 100 images inherit it as long as the image style has CROP in the name.
   */
  protected function getFirstImage($row): array {
    if (!isset($this->firstImage)) {
      $view = $this->view;
      // Fixed for Undefined property: Drupal\views\ViewExecutable::$row_index
      // by Drupal\views\Plugin\views\field\EntityField->prepareItemsByDelta.
      if (!isset($view->row_index)) {
        $view->row_index = 0;
      }

      $rendered = [];
      if ($row && $view->rowPlugin->render($row)) {
        if ($fields = $view->field ?? []) {
          foreach ($fields as $field) {
            $options = $field->options ?? [];
            $id = $options['plugin_id'] ?? '';
            $type = $options['type'] ?? $id;

            $doable = isset($options['media_switch'])
              || isset($options['settings']['image_style']);

            if (!$type) {
              continue;
            }

            if (!empty($options['field']) && $doable) {
              $name = $options['field'];
            }
          }

          if (isset($name)) {
            // Blazy Views field plugins: Blazy File and Media.
            if (strpos($name, 'blazy_') !== FALSE
            && $field = ($view->field[$name] ?? NULL)) {
              $result['rendered'] = $field->render($row);
            }
            else {
              // Blazy, Splide, Slick, etc. field formatters.
              $result = $this->getFieldRenderable($row, 0, $name);
            }

            if ($result
              && is_array($result)
              && isset($result['rendered'])
              && !($result['rendered'] instanceof Markup)) {
              // D10/9.5.10 moves it into indices.
              $rendered = $result['rendered'][0]['#build']
                ?? $result['rendered']['#build'] ?? $result['rendered'] ?? [];
            }
          }
        }
      }

      $this->firstImage = $rendered;
    }
    return $this->firstImage;
  }

  /**
   * Returns the renderable array of field containing rendered and raw data.
   */
  protected function getFieldRenderable($row, $index, $name, $multiple = FALSE): array {
    // Be sure to not check "Use field template" under "Style settings" to have
    // renderable array to work with, otherwise flattened string!
    if (!$name) {
      return [];
    }

    /** @var \Drupal\views\Plugin\views\field\EntityField $field */
    $field = $this->view->field[$name] ?? NULL;
    if ($field && method_exists($field, 'getItems')) {
      $result = $field->getItems($row);
      if ($result && is_array($result)) {
        // @todo recheck the last: a plain array, rendered/raw, markup, etc.
        return $multiple ? $result : ($result[0] ?? []);
      }
    }
    return [];
  }

  /**
   * Returns the rendered field, either string or array.
   */
  protected function getFieldRendered($index, $name, $restricted = FALSE): array {
    if ($name && $output = $this->getField($index, $name)) {
      return is_array($output) ? $output : [
        '#markup' => ($restricted ? Xss::filterAdmin($output) : $output),
      ];
    }
    return [];
  }

  /**
   * Provides a potential unique thumbnail different from the main image.
   *
   * Be sure to reset settings before calling this method:
   * $this->reset($sets);
   */
  protected function getThumbnail(array &$sets, $row, $index, $field_caption = NULL): array {
    $name    = $sets['thumbnail'] ?? NULL;
    $blazies = $sets['blazies'];

    $blazies->set('is.reset', TRUE);

    // Thumbnail image is optional for tab navigation like.
    [
      'doable' => $doable,
      'item' => $item,
    ] = $this->getWorkableThumbnail($sets, $row, $name);

    // Caption is optional for thumbed navigation only.
    $caption = [];
    if ($field_caption) {
      $caption = $this->getFieldRendered($index, $field_caption);
    }

    // Replace empty image item with the rendered output if not using image.
    if (!$doable && $name) {
      $item = $this->getFieldRendered($index, $name);
    }

    // If multiple, only one thumbnail can exist.
    return $this->manager->getThumbnail($sets, $item, $caption);
  }

  /**
   * Prepares commons settings for the style plugins.
   */
  protected function prepareSettings(array &$settings) {
    // Do nothing to let extenders modify.
  }

  /**
   * Sets dynamic html settings.
   */
  protected function setHtmlSettings(array $settings) {
    $this->htmlSettings = $settings;
    return $this;
  }

  /**
   * Renew settings per item.
   */
  protected function reset(array &$settings, $key = 'blazies', array $defaults = []) {
    return Internals::reset($settings, $key, $defaults);
  }

  /**
   * Provides a workable thumbnail if any.
   *
   * Be sure to reset settings before calling this method:
   * $this->reset($sets);
   */
  private function getWorkableThumbnail(array &$sets, $row, $name): array {
    if (!$name) {
      return ['doable' => FALSE, 'item' => NULL];
    }

    // Can only have one thumbnail even if multiple.
    // Supports core image formatter, the most sensible, and Blazy formatter.
    $blazies  = $sets['blazies'];
    $doable   = FALSE;
    $result   = $this->getFieldRenderable($row, 0, $name);
    $rendered = $result['rendered'] ?? [];
    $tn_style = $rendered['#image_style'] ?? NULL;
    $item     = $rendered['#item'] ?? NULL;
    $build    = $rendered['#build'] ?? [];

    // Might be group_rows, the first two are blazy, the last image_formatter.
    if (!$item) {
      $item = $build['#item'] ?? $build[0]['#item'] ?? $rendered['raw'] ?? NULL;
    }

    // If we have image style and image item.
    if ($tn_style && is_object($item)) {
      $uri = Blazy::uri($item);
      $sets['thumbnail_style'] = $tn_style;

      if (!$blazies->get('image.uri')) {
        $blazies->set('image.uri', $uri);
      }

      $tn_uri = $uri ? $this->manager
        ->load($tn_style, 'image_style')
        ->buildUri($uri) : NULL;

      // This allows a thumbnail different from the main stage, such as logos
      // thumbnails, and company buildings for the main stage.
      if ($tn_uri) {
        // @todo remove the first here.
        $sets['thumbnail_uri'] = $tn_uri;
        $blazies->set('thumbnail.uri', $tn_uri)
          ->set('thumbnail.item', $item);
        $doable = TRUE;
      }
      else {
        $doable = $blazies->get('image.uri') != NULL;
      }
    }
    return ['doable' => $doable, 'item' => $item];
  }

}
