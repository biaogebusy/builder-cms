<?php

namespace Drupal\blazy_layout;

use Drupal\blazy\BlazyDefault;
use Drupal\blazy\BlazyManager;
use Drupal\blazy\Utility\Arrays;
use Drupal\blazy\Utility\Css;
use Drupal\blazy_layout\BlazyLayoutDefault as Defaults;

/**
 * Provides BlazyLayoutManager utility.
 */
class BlazyLayoutManager extends BlazyManager implements BlazyLayoutManagerInterface {

  /**
   * {@inheritdoc}
   */
  protected static $namespace = 'blazy';

  /**
   * {@inheritdoc}
   */
  protected static $itemId = 'box';

  /**
   * {@inheritdoc}
   */
  protected static $itemPrefix = 'blazy';

  /**
   * {@inheritdoc}
   */
  protected static $captionId = 'blazy';

  /**
   * {@inheritdoc}
   */
  public function getClasses(array $settings): array {
    if ($classes = $settings['classes'] ?? '') {
      $classes = array_map(
        '\Drupal\Component\Utility\Html::cleanCssIdentifier',
        explode(' ', $classes)
      );
      return array_filter($classes);
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getKeys(array $elements): array {
    return array_keys(
      array_filter(
        $elements,
        fn($k) => strpos($k, '#') === FALSE,
        ARRAY_FILTER_USE_KEY
      )
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getRegions($count = NULL): array {
    $regions = [];
    $count = $count ?: Defaults::REGION_COUNT;

    foreach (range(1, $count) as $delta => $value) {
      $id    = Defaults::regionId($delta);
      $label = Defaults::regionLabel($delta);

      $regions[$id]['id']    = $id;
      $regions[$id]['delta'] = $delta;
      $regions[$id]['label'] = Defaults::regionTranslatableLabel($label);
      $regions[$id]['name']  = $id;
    }

    return $regions;
  }

  /**
   * {@inheritdoc}
   */
  public function layoutSettings(array $settings, $count): array {
    $settings['blazy_layout'] = TRUE;
    $settings['ete'] = FALSE;

    if ($layouts = $settings['styles']['layouts'] ?? []) {
      $settings['ete'] = !empty($layouts['ete']);
      $settings['gapless'] = !empty($layouts['gapless']);
    }

    $this->verifySafely($settings);
    $this->preSettings($settings);

    /** @var array $settings */
    $settings = $this->toSettings($settings);
    $blazies = $this->getBlazies($settings);

    $blazies->set('namespace', static::$namespace)
      ->set('is.grid', TRUE)
      ->set('is.lb', TRUE)
      ->set('grid.unlist', TRUE)
      ->set('grid.items', $settings['regions'] ?? [])
      ->set('item.id', static::$itemId)
      ->set('item.prefix', static::$itemPrefix)
      ->set('item.caption', static::$captionId)
      ->set('count', $count);

    if ($hero = $settings['hero'] ?? NULL) {
      $id = Defaults::regionId($hero);
      if ($styles = $settings['regions'][$id]['settings']['styles']['media'] ?? []) {
        $blazies->set('heroes', $styles);

        foreach (BlazyDefault::imageStyles() as $key) {
          if (!$blazies->get('heroes.' . $key . '.style')) {
            if ($_style = ($styles[$key . '_style'] ?? '')) {
              if ($entity = $this->load($_style, 'image_style')) {
                $blazies->set('heroes.' . $key . '.style', $entity)
                  ->set('heroes.' . $key . '.id', $entity->id());
              }
            }
          }
        }
      }
    }

    $this->postSettings($settings);

    $settings = array_diff_key($settings, BlazyDefault::imageSettings());
    $settings = Arrays::filter($settings);

    // If Heroes, enable preloading.
    if ($blazies->get('heroes')) {
      $settings['preload'] = TRUE;
    }

    // If Semantic layout enabled, turn DIV into UL list.
    $this->semantic($settings);

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function parseClasses(array &$output, array $settings): void {
    if ($classes = $this->getClasses($settings)) {
      foreach ($classes as $class) {
        $output['#attributes']['class'][] = $class;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function selector($key, $region, array $options = []): string {
    $empty    = $options['empty'] ?? FALSE;
    $block_bg = $options['block_bg'] ?? FALSE;
    $prefix   = '.region';
    $semantic = $options['semantic'] ?? FALSE;

    if ($region) {
      $region = str_replace('_', '-', $region);
      $prefix = ".region--{$region}";
    }

    if ($region == 'bg') {
      if (!in_array($key, ['background', 'overlay'])) {
        $prefix = '.region';
      }

      if ($semantic) {
        $prefix = '';
      }
    }

    switch ($key) {
      case 'padding':
        return $region == 'bg' ? '' : $prefix;

      case 'background':
        if ($semantic && $region == 'bg') {
          return 'SEMANTIC_BG';
        }
        return $empty || !$block_bg ? "{$prefix}, {$prefix} .b-bg" : "{$prefix} .b-bg";

      case 'overlay':
        return "{$prefix} .media__overlay";

      case 'text':
        return "{$prefix} p";

      case 'heading':
        return "{$prefix} h2, {$prefix} h3, {$prefix} .field__label";

      case 'link':
        return "{$prefix} a";

      case 'link_hover':
        return "{$prefix} a:hover";

      default:
        return '';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function toRules(array $data, array $options): string {
    $id = $options['id'] ?? '';
    $custom_css = $options['custom_css'] ?? '';
    $semantic = $options['semantic_layout'] ?? FALSE;

    $css = implode(' ', array_map(
      function ($value, $key) use ($id, $semantic) {
        if (strpos($value, 'ROOT') !== FALSE) {
          return str_replace('ROOT', ".blazy.b-layout.{$id}", $value);
        }

        if ($key === 'SEMANTIC_BG') {
          if ($semantic) {
            $id = str_replace('b-layout--', '', $id);
            return ".b-semantic.b-layout-wrapper--{$id} .region--bg {{$value}}";
          }
        }

        // Multiple selectors.
        if (strpos($key, ',') !== FALSE) {
          $vals = array_map('trim', explode(',', $key));
          $sels = [];
          foreach ($vals as $val) {
            $sels[] = ".blazy.{$id} {$val}";
          }

          $selector = implode(', ', $sels);
          return "{$selector} {{$value}}";
        }

        // Single selector.
        return $id == $key
          ? ".blazy.b-layout.{$key} {{$value}}"
          : ".blazy.{$id} {$key} {{$value}}";
      },
      $data,
      array_keys($data)
    ));

    // UGC CSS requires hardened admin roles to enable the option at Blazy UI.
    if ($this->config('use_custom_css') && $custom_css) {
      $added_css = Css::sanitizeInline($custom_css);

      if ($scope = $this->config('css_scope')) {
        $added_css = Css::scope($added_css, $scope);
      }

      $css .= $added_css;
    }

    return $css;
  }

  /**
   * {@inheritdoc}
   */
  public function getMediaLibraries(): array {
    $libraries = [];
    $admin_theme = $this->config('admin', 'system.theme');

    // @todo remove once media_library is loaded at frontend modal.
    if ($this->moduleExists('media_library')) {
      $libraries[] = 'media_library/view';
      $libraries[] = 'media_library/ui';
      $libraries[] = 'media_library/widget';
    }

    if ($admin_theme == 'claro') {
      $libraries[] = 'claro/media_library.theme';
      $libraries[] = 'claro/media_library.ui';
    }
    elseif ($admin_theme == 'gin') {
      $libraries[] = 'gin/media_library';
    }

    // Adminimal, Classy has no special media library theme, skip.
    return $libraries;
  }

  /**
   * {@inheritdoc}
   */
  public function semantic(array &$settings): void {
    if (!empty($settings['semantic_layout'])) {
      $settings['wrapper'] = 'ul';

      if ($regions = $settings['regions'] ?? []) {
        foreach ($regions as $key => $region) {
          $settings['regions'][$key]['settings']['wrapper'] = 'li';
        }
      }
    }
  }

}
