<?php

namespace Drupal\field_widget_actions;

/**
 * Interface for FieldWidgetActionManager.
 */
interface FieldWidgetActionManagerInterface {

  /**
   * Gets allowed field widget actions for given field type and widget.
   *
   * @param string $widget_type
   *   The field widget type.
   * @param string $field_type
   *   The field type.
   *
   * @return \Drupal\field_widget_actions\FieldWidgetActionInterface[]
   *   The list of allowed plugins.
   */
  public function getAllowedFieldWidgetActions($widget_type, $field_type): array;

  /**
   * Gets allowed form-based field widget actions.
   *
   * @return \Drupal\field_widget_actions\FieldWidgetFormActionInterface[]
   *   The list of allowed form-based plugins.
   */
  public function getFieldWidgetActionFormDefinitions(): array;

}
