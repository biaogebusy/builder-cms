<?php

namespace Drupal\slick\Plugin\Field\FieldFormatter;

use Drupal\blazy\Plugin\Field\FieldFormatter\BlazyFileFormatterBase;
use Drupal\Component\Utility\Xss;
use Drupal\slick\SlickDefault;

/**
 * Base class for slick image and file ER formatters.
 *
 * @todo extends BlazyFileSvgFormatterBase post blazy:2.17, or split.
 */
abstract class SlickFileFormatterBase extends BlazyFileFormatterBase {

  use SlickFormatterTrait;

  /**
   * {@inheritdoc}
   */
  protected static $namespace = 'slick';

  /**
   * {@inheritdoc}
   */
  protected static $itemId = 'slide';

  /**
   * {@inheritdoc}
   */
  protected static $itemPrefix = 'slide';

  /**
   * {@inheritdoc}
   */
  protected static $captionId = 'caption';

  /**
   * {@inheritdoc}
   */
  protected static $navId = 'thumb';

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return SlickDefault::imageSettings() + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   *
   * @todo remove it into self::withElementOverride() post blazy:2.17.
   */
  public function buildElements(array &$build, $files, $langcode) {
    foreach ($this->getElements($build, $files) as $element) {
      if ($element) {
        // Build individual item.
        $build['items'][] = $element;

        // Build individual thumbnail.
        $this->withElementThumbnail($build, $element);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function withElementThumbnail(array &$build, array $element): void {
    if (!$build['#asnavfor']) {
      return;
    }

    // The settings in $element has updated metadata extracted from media.
    $settings = $this->formatter->toHashtag($element);
    $item     = $this->formatter->toHashtag($element, 'item', NULL);
    $_caption = $settings['thumbnail_caption'] ?? NULL;
    $caption  = [];

    if ($_caption && $item && $text = $item->{$_caption} ?? NULL) {
      $caption = ['#markup' => Xss::filterAdmin($text)];
    }

    // Thumbnail usages: asNavFor pagers, dot, arrows thumbnails.
    $tn = $this->formatter->getThumbnail($settings, $item, $caption);
    $build[static::$navId]['items'][] = $tn;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPluginScopes(): array {
    $captions = ['title' => $this->t('Title'), 'alt' => $this->t('Alt')];

    return [
      'namespace'       => 'slick',
      'nav'             => TRUE,
      'thumb_captions'  => $captions,
      'thumb_positions' => TRUE,
    ] + parent::getPluginScopes();
  }

}
