<?php

namespace Drupal\shs;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Helper class for setting default values on SHS widgets.
 */
class WidgetDefaults implements WidgetDefaultsInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * WidgetDefaults constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Gets an initial default values array.
   *
   * @param string $default_value
   *   The default value.
   * @param int $cardinality
   *   (optional) The field's cardinality. Defaults to 1.
   *
   * @return array
   *   The initial default value array.
   */
  public function getInitialParentDefaults($default_value, $cardinality = 1) {
    $parents = [];

    // In case of unlimited(-1) we also need one iteration.
    if ($cardinality < 0) {
      $cardinality = 1;
    }

    for ($i = 1; $i <= $cardinality; $i++) {
      $parents[] = [
        [
          'parent' => 0,
          'defaultValue' => $default_value,
        ],
      ];
    }

    return $parents;
  }

  /**
   * Load parents for default values.
   *
   * @param array|string $default_values
   *   List of default values of the widget.
   * @param string $any_value
   *   The value to assign to "any value".
   * @param string $entity_type
   *   The entity type the select is displaying.
   * @param int $cardinality
   *   The field's cardinality.
   *
   * @return array
   *   List of parents for each default value.
   */
  public function getParentDefaults($default_values, $any_value, $entity_type, $cardinality = 1) {
    $parents = [];
    if (!is_array($default_values)) {
      $default_values = [$default_values];
    }
    foreach ($default_values as $delta => $value) {
      if ($any_value === $value) {
        $parents[$delta] = [
          [
            'parent' => 0,
            'defaultValue' => $any_value,
          ],
        ];
        continue;
      }
      try {
        $parent_terms = array_reverse(array_keys(shs_term_load_all_parents($value)));
        $keys = array_merge([0], $parent_terms);
        $values = array_merge($parent_terms, [$value]);
        $parents[$delta] = [];
        foreach ($keys as $index => $key) {
          $parents[$delta][] = [
            'parent' => $key,
            'defaultValue' => $values[$index] ?: $any_value,
          ];
        }
      }
      catch (\Exception $ex) {
        $parents[$delta] = [
          [
            'parent' => 0,
            'defaultValue' => $any_value,
          ],
        ];
      }
    }

    // Populate the parents array so it's equal to the field's cardinality.
    if ($cardinality < 0) {
      $cardinality = 1;
    }
    $parents_count = count($parents);
    if ($parents_count >= $cardinality) {
      return $parents;
    }
    for ($i = 1; $i <= ($cardinality - $parents_count); $i++) {
      $parents[] = [
        [
          'parent' => 0,
          'defaultValue' => $any_value,
        ],
      ];
    }

    return $parents;
  }

}
