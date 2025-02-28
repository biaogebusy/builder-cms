<?php

namespace Drupal\metatag_favicons\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\LinkRelBase;

/**
 * This base plugin allows "link rel" tags with a "sizes" attribute.
 */
abstract class LinkSizesBase extends LinkRelBase {

  /**
   * {@inheritdoc}
   */
  public function output() {
    $element = parent::output();

    if ($element) {
      $element['#attributes'] = [
        'rel' => $this->name(),
        'sizes' => $this->iconSize(),
        'href' => $element['#attributes']['href'],
      ];
    }

    return $element;
  }

  /**
   * The dimensions supported by this icon.
   *
   * @return string
   *   A string in the format "XxY" for a given width and height.
   */
  protected function iconSize() {
    return '';
  }

  /**
   * The dimensions supported by this icon.
   *
   * @return string
   *   A string in the format "XxY" for a given width and height.
   *
   * @deprecated in 8.x-1.22 and is removed from 2.0.0. Use iconSize() instead.
   *
   * @see https://www.drupal.org/node/3300522
   */
  protected function sizes() {
    return $this->iconSize();
  }

}
