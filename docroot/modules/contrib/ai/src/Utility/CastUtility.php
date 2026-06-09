<?php

namespace Drupal\ai\Utility;

/**
 * Utility class for typecasting.
 */
class CastUtility {

  /**
   * Does type casting for configurations.
   *
   * @param string $type
   *   Expected type of the data in the settings.
   * @param mixed $value
   *   Value of the data.
   *
   * @return mixed
   *   Type-casted value.
   */
  public static function typeCast(string $type, mixed $value): mixed {
    switch ($type) {
      case 'int':
      case 'integer':
        $value = (int) $value;
        break;

      case 'float':
        $value = (float) $value;
        break;

      case 'bool':
      case 'boolean':
        if (is_string($value)) {
          $value = strtolower($value) === 'true';
        }
        else {
          $value = (bool) $value;
        }
        break;

      case 'array':
        $value = (array) $value;
        break;

      case 'string':
        $value = (string) $value;
        break;
    }

    return $value;
  }

}
