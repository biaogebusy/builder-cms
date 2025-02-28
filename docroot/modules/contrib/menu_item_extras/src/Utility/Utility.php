<?php

namespace Drupal\menu_item_extras\Utility;

/**
 * Utility functions specific to menu_item_extras.
 */
class Utility {

  /**
   * Checks if bundle and entity fields are different.
   *
   * @param string $entity_type
   *   Entity type for checking.
   * @param string $bundle
   *   Bundle for checking.
   *
   * @return bool
   *   Returns TRUE if bundle has other fields than entity.
   */
  public static function checkBundleHasExtraFieldsThanEntity($entity_type, $bundle) {
    $entity_manager = \Drupal::service('entity_field.manager');
    $bundle_fields = array_keys($entity_manager->getFieldDefinitions($entity_type, $bundle));
    $entity_type_fields = array_keys($entity_manager->getBaseFieldDefinitions($entity_type));
    if ($bundle_fields !== $entity_type_fields) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Sanitize string.
   *
   * @param string $string
   *   Input string.
   *
   * @return string
   *   Sanitized string.
   */
  public static function sanitizeMachineName($string) {
    $to_replace = [' ', '-', '.'];
    $string = str_replace(
      $to_replace,
      array_fill(0, count($to_replace), '_'),
      mb_strtolower($string)
    );
    $string = preg_replace('/[^A-Za-z0-9\_]/', '', $string);
    return self::limitUnderscores($string);
  }

  /**
   * Suggestion builder, based on recieved args.
   *
   * @param string $str1
   *   Argument for string building.
   * @param string $str2
   *   Add arguments as more as you need.
   *
   * @return string
   *   Prepared suggestion string.
   */
  public static function suggestion($str1, $str2 = '') {
    return self::limitUnderscores(implode('__', func_get_args()));
  }

  /**
   * Filter more than 2 underscores in the row.
   *
   * @param string $str
   *   Input string.
   *
   * @return null|string
   *   Filtered string.
   */
  public static function limitUnderscores($str) {
    return preg_replace('/__+/', '__', $str);
  }

}
