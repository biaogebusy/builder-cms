<?php

declare(strict_types = 1);

namespace Drupal\entity_share_test;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides helper functions to get field values.
 */
trait EntityFieldHelperTrait {

  /**
   * Retrieve the value from a field.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param string $field_name
   *   The field to retrieve the value.
   *
   * @return array
   *   The field values. Empty if there is no field with this machine name or if
   *   there is no value.
   */
  public function getValues(ContentEntityInterface $entity, string $field_name) {
    $values = [];

    if ($entity->hasField($field_name) && !$entity->get($field_name)->isEmpty()) {
      $values = $entity->get($field_name)->getValue();
    }

    return $values;
  }

  /**
   * Retrieve the value from a field.
   *
   * Properties not in the expected structure are removed.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param string $field_name
   *   The field to retrieve the value.
   * @param array $structure
   *   The initial data structure.
   *
   * @return array
   *   The field values. Empty if there is no field with this machine name or if
   *   there is no value.
   */
  public function getFilteredStructureValues(ContentEntityInterface $entity, string $field_name, array $structure) {
    $values = [];

    if ($entity->hasField($field_name) && !$entity->get($field_name)->isEmpty()) {
      $values = $entity->get($field_name)->getValue();

      // Remove unexpected properties.
      foreach ($values as $delta => $value) {
        $values[$delta] = array_intersect_key($value, array_flip($structure));
      }
    }

    return $values;
  }

  /**
   * Retrieve the value from a field.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param string $field_name
   *   The field to retrieve the value.
   *
   * @return string
   *   The field value. Empty if there is no field with this machine name or if
   *   there is no value.
   */
  public function getValue(ContentEntityInterface $entity, string $field_name) {
    $value = '';

    if ($entity->hasField($field_name) && !$entity->get($field_name)->isEmpty()) {
      $value = $entity->get($field_name)->getValue()[0]['value'];
    }

    return (string) $value;
  }

  /**
   * Retrieve the value from a field where the value key is target_id.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param string $field_name
   *   The field to retrieve the value.
   *
   * @return string
   *   The field value. Empty if there is no field with this machine name or if
   *   there is no value.
   */
  public function getTargetId(ContentEntityInterface $entity, string $field_name) {
    $value = '';

    if ($entity->hasField($field_name) && !$entity->get($field_name)->isEmpty()) {
      $value = $entity->get($field_name)->getValue()[0]['target_id'];
    }

    return (string) $value;
  }

}
