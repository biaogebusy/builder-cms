<?php

namespace Drupal\blazy\Field;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Render\Element;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\Internals\Entity;
use Drupal\blazy\Internals\Field;

/**
 * Provides common field API operation methods.
 *
 * @todo move some into FieldTrait for DI at D11.
 */
class BlazyField {

  /**
   * Returns the string value of the fields: link, or text.
   */
  public static function getString($entity, $field_name, $langcode, $clean = TRUE): string {
    if ($entity->hasField($field_name)) {
      $values = self::getValue($entity, $field_name, $langcode);

      // Can be text, or link field.
      $string = $values[0]['uri'] ?? ($values[0]['value'] ?? '');

      if ($string && is_string($string)) {
        $string = $clean
          ? strip_tags($string, '<a><strong><em><span><small>')
          : Xss::filter($string, BlazyDefault::TAGS);
        return trim($string);
      }
    }
    return '';
  }

  /**
   * Returns the text or link value of the fields: link, or text.
   */
  public static function getTextOrLink($entity, $field_name, $view_mode, $langcode, $multiple = TRUE): array {
    if ($entity->hasField($field_name)) {
      if ($text = self::getValue($entity, $field_name, $langcode)) {
        if (!empty($text[0]['value']) && !isset($text[0]['uri'])) {
          // Prevents HTML-filter-enabled text from having bad markups (h2 > p),
          // except for a few reasonable tags acceptable within H2 tag.
          $text = self::getString($entity, $field_name, $langcode, FALSE);
        }
        elseif (isset($text[0]['uri'])) {
          $text = self::view($entity, $field_name, $view_mode, $multiple);
        }

        // Prevents HTML-filter-enabled text from having bad markups
        // (h2 > p), save for few reasonable tags acceptable within H2 tag.
        return is_string($text)
          ? ['#markup' => strip_tags($text, '<a><strong><em><span><small>')]
          : $text;
      }
    }
    return [];
  }

  /**
   * Returns the value of the fields: link, or text.
   */
  public static function getValue($entity, $field_name, $langcode) {
    if ($entity->hasField($field_name)) {
      $entity = Entity::translated($entity, $langcode);

      return $entity->get($field_name)->getValue();
    }
    return NULL;
  }

  /**
   * Returns the formatted renderable array of the field.
   */
  public static function view($entity, $field_name, $view_mode, $multiple = TRUE): array {
    if ($entity && $entity->hasField($field_name)) {
      $view = $entity->get($field_name)->view($view_mode);

      if (empty($view[0])) {
        return [];
      }

      // Prevents quickedit to operate here as otherwise JS error.
      // @see 2314185, 2284917, 2160321.
      // @see quickedit_preprocess_field().
      // @todo Remove when it respects plugin annotation.
      $view['#view_mode'] = '_custom';
      $weight = $view['#weight'] ?? 0;

      // Intentionally clean markups as this is not meant for vanilla.
      if ($multiple) {
        $items = [];
        foreach (Element::children($view) as $key) {
          $items[$key] = $view[$key];
        }

        $items['#weight'] = $weight;
        return $items;
      }
      return $view[0] ?? [];
    }

    return [];
  }

  /**
   * Alias for Field::getAvailableBundles().
   *
   * @todo deprecate and remove at D11.
   */
  public static function getAvailableBundles($field): array {
    return Field::getAvailableBundles($field);
  }

  /**
   * Alias for Field::settings().
   *
   * @todo deprecate and remove at D11.
   */
  public static function settings(array &$settings, $field, array $data = []): array {
    return Field::settings($settings, $field, $data);
  }

}
