<?php

namespace Drupal\blazy_layout;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\blazy\BlazyDefault;

/**
 * Defines shared plugin default settings for field formatter and Views style.
 */
class BlazyLayoutDefault {

  /**
   * Defines region count.
   */
  const REGION_COUNT = 9;

  /**
   * Returns display style options, different from core Blazy for layouts.
   *
   * @return array
   *   The display style settings.
   */
  public static function displayStyle(): array {
    return [
      'column' => 'CSS3 Columns',
      'grid' => 'Grid Foundation',
      'flexbox' => 'Flexbox',
      'nativegrid' => 'Native Grid',
    ];
  }

  /**
   * Returns sensible default options common for entities lacking of UI.
   *
   * @return array
   *   The entity settings.
   */
  public static function entitySettings(): array {
    return BlazyDefault::entitySettings();
  }

  /**
   * Returns the hero settings.
   *
   * @return array
   *   The layout settings.
   */
  public static function heroSettings(): array {
    return [
      'hero'            => '',
      'custom_css'      => '',
      'remove_bg'       => FALSE,
      'semantic_layout' => FALSE,
    ];
  }

  /**
   * Returns the layout settings.
   *
   * @return array
   *   The layout settings.
   */
  public static function layoutSettings(): array {
    return [
      'id'             => '',
      'regions'        => [],
      'count'          => static::REGION_COUNT,
      'style'          => 'nativegrid',
      'grid'           => '4x4 4x3 2x2 2x4 2x2 2x3 2x3 4x2 4x2',
      'grid_medium'    => '3',
      'grid_small'     => '1',
      'grid_auto_rows' => '',
      'align_items'    => '',
    ] + self::sharedSettings() + self::heroSettings();
  }

  /**
   * Returns the sub-layout settings.
   *
   * @return array
   *   The sublayout settings.
   */
  public static function sublayoutSettings(): array {
    return [
      'ete'       => FALSE,
      'gapless'   => FALSE,
      'padding'   => '',
      'max_width' => '',
    ];
  }

  /**
   * Returns the media settings.
   *
   * @return array
   *   The layout media settings.
   */
  public static function layoutMediaSettings(): array {
    return [
      'id' => '',
      'background' => TRUE,
      'media_switch' => '',
      'image_style' => '',
      'responsive_image_style' => '',
      'box_caption' => '',
      'box_style' => '',
      'box_media_style' => '',
      'ratio' => 'fluid',
      'link' => '',
      // @todo remove after an update.
      'use_player' => FALSE,
    ];
  }

  /**
   * Returns the region layout settings.
   *
   * @return array
   *   The region settings.
   */
  public static function regionSettings(): array {
    return [
      'label'    => '',
      'settings' => self::sharedSettings(),
    ];
  }

  /**
   * Returns the region layout settings.
   *
   * @return array
   *   The style settings.
   */
  public static function styleSettings(): array {
    return [
      'background_color'   => '',
      'background_opacity' => '1',
      'overlay_color'      => '',
      'overlay_opacity'    => '1',
      'heading_color'      => '',
      'heading_opacity'    => '1',
      'text_color'         => '',
      'text_opacity'       => '1',
      'link_color'         => '',
      'link_hover_color'   => '',
    ];
  }

  /**
   * Returns align items options.
   *
   * @return array
   *   The align_items settings.
   */
  public static function alignItems(): array {
    return [
      'normal' => 'normal',
      'stretch' => 'stretch',
      'center' => 'center',
      'start' => 'start',
      'end' => 'end',
      'flex-start' => 'flex-start',
      'flex-end' => 'flex-end',
      'self-start' => 'self-start',
      'self-end' => 'self-end',
      'baseline' => 'baseline',
      'first baseline' => 'first baseline',
      'last baseline' => 'last baseline',
      'safe center' => 'safe center',
      'unsafe center' => 'unsafe center',
      'inherit' => 'inherit',
      'initial' => 'initial',
      'revert' => 'revert',
      'revert-layer' => 'revert-layer',
      'unset' => 'unset',
    ];
  }

  /**
   * Returns the main wrapper Layout Builder select options.
   *
   * @return array
   *   The main wrapper options.
   */
  public static function mainWrapperOptions(): array {
    return [
      'div'     => 'Div',
      'article' => 'Article',
      'aside'   => 'Aside',
      'main'    => 'Main',
      'footer'  => 'Footer',
      'section' => 'Section',
    ];
  }

  /**
   * Returns wrapper Layout Builder select options.
   *
   * @return array
   *   The region wrapper options.
   */
  public static function regionWrapperOptions(): array {
    return self::mainWrapperOptions() + [
      'figure' => 'Figure',
      'header' => 'Header',
    ];
  }

  /**
   * Returns layout ID.
   *
   * @param string|int $id
   *   The layout ID.
   *
   * @return string
   *   The standardized layout ID.
   */
  public static function layoutId($id): string {
    return 'b-layout--' . (string) $id;
  }

  /**
   * Returns layout id.
   *
   * @param string $label
   *   The layout label.
   *
   * @return string
   *   The standardized layout label.
   */
  public static function layoutLabel($label): string {
    return 'Blazy: ' . (string) $label;
  }

  /**
   * Returns region ID.
   *
   * @param string|int $id
   *   The region ID.
   *
   * @return string
   *   The standardized region ID.
   */
  public static function regionId($id): string {
    return 'blzyr_' . (string) $id;
  }

  /**
   * Returns region label.
   *
   * @param string|int $label
   *   The layout label.
   *
   * @return string
   *   The standardized region label.
   */
  public static function regionLabel($label): string {
    return 'Region ' . (string) $label;
  }

  /**
   * Returns region label.
   *
   * @param string|int $label
   *   The layout label.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The translatable region label.
   */
  public static function regionTranslatableLabel($label): TranslatableMarkup {
    return new TranslatableMarkup('@label', ['@label' => $label], [
      'context' => 'layout_region',
    ]);
  }

  /**
   * Returns the shared settings.
   *
   * @return array
   *   The shared settings.
   */
  public static function sharedSettings(): array {
    return [
      'wrapper'     => 'div',
      'attributes'  => '',
      'classes'     => '',
      'row_classes' => '',
      'styles'      => [
        'colors'  => self::styleSettings(),
        'layouts' => self::sublayoutSettings(),
        'media' => self::layoutMediaSettings(),
      ],
    ];
  }

}
