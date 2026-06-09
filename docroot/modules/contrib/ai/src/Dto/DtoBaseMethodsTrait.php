<?php

namespace Drupal\ai\Dto;

/**
 * Provides base methods for Data Transfer Objects (DTOs).
 */
trait DtoBaseMethodsTrait {

  /**
   * Creates a new DTO instance from an array of values.
   *
   * @param array $values
   *   An associative array of property values.
   *
   * @return static
   *   A new instance of the DTO.
   */
  public static function create(array $values): self {
    $dto = new self();

    foreach ($values as $key => $value) {
      if (property_exists($dto, $key)) {
        $dto->$key = $value;
      }
    }

    return $dto;
  }

  /**
   * Converts the DTO to an associative array.
   *
   * @return array
   *   An array of property values.
   */
  public function toArray(): array {
    return get_object_vars($this);
  }

}
