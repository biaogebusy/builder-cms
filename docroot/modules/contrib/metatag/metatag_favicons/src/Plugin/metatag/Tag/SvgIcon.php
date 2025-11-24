<?php

namespace Drupal\metatag_favicons\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\LinkRelBase;

/**
 * The SVG Favicon meta tag.
 *
 * @MetatagTag(
 *   id = "svg_icon",
 *   label = @Translation("SVG icon"),
 *   description = @Translation("A scalable vector graphic (SVG) file."),
 *   name = "icon",
 *   group = "favicons",
 *   weight = 2,
 *   type = "image",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SvgIcon extends LinkRelBase {

  /**
   * {@inheritdoc}
   */
  public function output(): array {
    $element = parent::output();
    if ($element) {
      $element['#attributes']['type'] = 'image/svg+xml';
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getTestOutputExistsXpath(): array {
    return ["//link[@rel='{$this->name}' and @type='image/svg+xml']"];
  }

  /**
   * {@inheritdoc}
   */
  public function getTestOutputValuesXpath(array $values): array {
    $xpath_strings = [];
    foreach ($values as $value) {
      $xpath_strings[] = "//link[@rel='{$this->name}' and @type='image/svg+xml' and @" . $this->htmlValueAttribute . "='{$value}']";
    }
    return $xpath_strings;
  }

}
