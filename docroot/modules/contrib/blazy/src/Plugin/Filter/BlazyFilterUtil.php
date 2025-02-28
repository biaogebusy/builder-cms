<?php

namespace Drupal\blazy\Plugin\Filter;

/**
 * Provides shared filter utilities.
 *
 * @todo deprecated in 2.17 and is removed from 3.x. Use self methods instead.
 * @see https://www.drupal.org/node/3103018
 */
class BlazyFilterUtil extends Shortcode {

  /**
   * Returns settings for attachments.
   *
   * @todo deprecated in 2.17 and is removed from 3.x. Use self::attach()
   * instead.
   * @see https://www.drupal.org/node/3103018
   */
  public static function attach(array $settings = []): array {
    // @trigger_error('attach is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use self::attach() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    $all = ['blazy' => TRUE, 'filter' => TRUE, 'ratio' => TRUE] + $settings;
    $all['media_switch'] = $switch = $settings['media_switch'] ?? '';

    if (!empty($settings[$switch])) {
      $all[$switch] = $settings[$switch];
    }

    return $all;
  }

  /**
   * Returns the inner HTMLof the DOMElement node.
   *
   * See https://www.php.net/manual/en/class.domelement.php#101243
   *
   * @todo deprecated in 2.17 and is removed from 3.x. Use self::getHtml()
   * instead.
   * @see https://www.drupal.org/node/3103018
   */
  public static function getHtml(\DOMElement $node): ?string {
    // @trigger_error('getHtml is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use self::getHtml() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    $text = '';
    foreach ($node->childNodes as $child) {
      if ($child instanceof \DOMElement) {
        $text .= $child->ownerDocument->saveXML($child);
      }
    }
    return $text;
  }

  /**
   * Returns DOMElement nodes expected to be grid, or slide items.
   *
   * @todo deprecated in 2.17 and is removed from 3.x. Use self::getNodes()
   * instead.
   * @see https://www.drupal.org/node/3103018
   */
  public static function getNodes(\DOMDocument $dom, $tag = '//grid') {
    // @trigger_error('getNodes is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use self::getNodes() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    $xpath = new \DOMXPath($dom);

    return $xpath->query($tag);
  }

  /**
   * Returns a valid node, excluding blur/ noscript images.
   *
   * @todo deprecated in 2.17 and is removed from 3.x. Use self::getValidNode()
   * instead.
   * @see https://www.drupal.org/node/3103018
   */
  public static function getValidNode($children) {
    // @trigger_error('getValidNode is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use self::getValidNode() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    $child = $children->item(0);
    $class = $child->getAttribute('class');
    $is_blur = $class && strpos($class, 'b-blur') !== FALSE;
    $is_bg = $class && strpos($class, 'b-bg') !== FALSE;

    if ($is_blur && !$is_bg) {
      $child = $children->item(1) ?: $child;
    }
    return $child;
  }

  /**
   * Removes nodes.
   *
   * @todo deprecated in 2.17 and is removed from 3.x. Use self::removeNodes()
   * instead.
   * @see https://www.drupal.org/node/3103018
   */
  public static function removeNodes(&$nodes): void {
    @trigger_error('removeNodes is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use self::removeNodes() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    foreach ($nodes as $node) {
      if ($node->parentNode) {
        $node->parentNode->removeChild($node);
      }
    }
  }

  /**
   * Return valid nodes based on the allowed tags.
   *
   * @todo deprecated in 2.17 and is removed from 3.x. Use self::validNodes()
   * instead.
   * @see https://www.drupal.org/node/3103018
   */
  public static function validNodes(\DOMDocument $dom, array $allowed_tags = [], $exclude = ''): array {
    // @trigger_error('validNodes is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use self::validNodes() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    $valid_nodes = [];
    foreach ($allowed_tags as $allowed_tag) {
      $nodes = $dom->getElementsByTagName($allowed_tag);
      if (property_exists($nodes, 'length') && $nodes->length > 0) {
        foreach ($nodes as $node) {
          if ($exclude && $node->hasAttribute($exclude)) {
            continue;
          }

          $valid_nodes[] = $node;
        }
      }
    }
    return $valid_nodes;
  }

}
