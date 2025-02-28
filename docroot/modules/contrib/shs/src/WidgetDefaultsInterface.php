<?php

namespace Drupal\shs;

/**
 * Interface for default values on SHS widgets.
 */
interface WidgetDefaultsInterface {

  /**
   * Gets an initial default values array.
   *
   * @param string $default_value
   *   The default value.
   *
   * @return array
   *   The initial default value array.
   */
  public function getInitialParentDefaults($default_value);

  /**
   * Load parents for default values.
   *
   * @param array|string $default_values
   *   List of default values of the widget.
   * @param string $any_value
   *   The value to assign to "any value".
   * @param string $entity_type
   *   The entity type the select is displaying.
   *
   * @return array
   *   List of parents for each default value.
   */
  public function getParentDefaults($default_values, $any_value, $entity_type);

}
