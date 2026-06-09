<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The Chart action.
 */
#[FieldWidgetAction(
  id: 'automator_chart',
  label: new TranslatableMarkup('Automator Chart'),
  widget_types: ['chart_config_default'],
  field_types: ['chart_config'],
)]
class Chart extends AutomatorBaseAction {

  /**
   * {@inheritdoc}
   */
  public string $formElementProperty = 'config';

  /**
   * Ajax handler for Automators.
   */
  public function aiAutomatorsAjax(array &$form, FormStateInterface $form_state) {
    // Get the triggering element, as it contains the settings.
    $triggering_element = $form_state->getTriggeringElement();
    $array_parents = $triggering_element['#array_parents'];
    array_pop($array_parents);
    $array_parents[] = $this->formElementProperty;
    $key = $array_parents[2] ?? 0;
    $form_key = $array_parents[0];

    return $this->populateAutomatorValues($form, $form_state, $form_key, $key);
  }

  /**
   * {@inheritdoc}
   */
  protected function saveFormValues(array &$form, string $form_key, $entity, ?int $key = NULL): array {
    if (is_null($key)) {
      // If no key is provided, we should iterate through all items.
      foreach ($entity->get($form_key) as $index => $item) {
        if ($item->get($this->formElementProperty)) {
          $value = $item->get($this->formElementProperty)->getValue();
          if (isset($value['series']['data_collector_table'])) {
            $table_element = &$form[$form_key]['widget'][$index]['config']['series']['data_collector_table'];
            $this->populateTableValues($table_element, $value['series']['data_collector_table']);
          }
        }
      }
    }
    else {
      // Handle specific key/index.
      if (isset($entity->get($form_key)[$key])) {
        $item = $entity->get($form_key)[$key];
        if ($item && $item->get($this->formElementProperty)) {
          $value = $item->get($this->formElementProperty)->getValue();
          if (isset($value['series']['data_collector_table'])) {
            $table_element = &$form[$form_key]['widget'][$key]['config']['series']['data_collector_table'];
            $this->populateTableValues($table_element, $value['series']['data_collector_table']);
          }
        }
      }
    }

    return $form[$form_key];
  }

  /**
   * Helper to populate table values.
   */
  protected function populateTableValues(array &$element, array $values) {
    // Ensure values is actually an array.
    if (!is_array($values)) {
      return;
    }

    if (!empty($values)) {
      $element['#value'] = $values;
      $element['#default_value'] = $values;

      // Only use integer keys for mapping (exclude 'delete',
      // '_delete_column_buttons', '_operations', etc.).
      $existing_row_keys = array_values(array_filter(array_keys($element), 'is_int'));

      foreach ($values as $row_key => $row_data) {
        // Skip non-numeric keys like _delete_column_buttons, _operations.
        if (!is_int($row_key)) {
          continue;
        }

        // Ensure row_data is an array before processing.
        if (!is_array($row_data)) {
          continue;
        }

        $target_row_key = $existing_row_keys[$row_key] ?? $row_key;

        if (!isset($element[$target_row_key])) {
          $element[$target_row_key] = [];
        }

        // Only use integer keys for column mapping (exclude 'delete',
        // 'weight', etc.).
        $existing_col_keys = array_values(array_filter(
          array_keys($element[$target_row_key]),
          'is_int'
        ));

        foreach ($row_data as $col_key => $cell_data) {
          // Skip non-numeric keys like delete.
          if (!is_int($col_key)) {
            continue;
          }

          $target_col_key = $existing_col_keys[$col_key] ?? $col_key;

          // Normalize cell_data - handle cases where it might be a
          // TranslatableMarkup or string.
          if (!is_array($cell_data)) {
            $cell_data = [
              'data' => $cell_data instanceof TranslatableMarkup ? $cell_data->__toString() : (string) $cell_data,
            ];
          }

          // If the cell doesn't exist, clone from an existing cell in the
          // same row and update its identity properties.
          if (!isset($element[$target_row_key][$target_col_key]) && !empty($existing_col_keys)) {
            $source_col_key = end($existing_col_keys);
            $element[$target_row_key][$target_col_key] = $this->cloneCellElement(
              $element[$target_row_key][$source_col_key],
              $source_col_key,
              $target_col_key
            );
          }

          if (isset($element[$target_row_key][$target_col_key]['data']) && isset($cell_data['data'])) {
            $data_value = $cell_data['data'] instanceof TranslatableMarkup ? $cell_data['data']->__toString() : $cell_data['data'];
            $element[$target_row_key][$target_col_key]['data']['#value'] = $data_value;
            $element[$target_row_key][$target_col_key]['data']['#default_value'] = $data_value;
          }
          if (isset($cell_data['color'])) {
            // Ensure color sub-element exists by cloning from data if needed.
            if (!isset($element[$target_row_key][$target_col_key]['color']) && isset($element[$target_row_key][$target_col_key]['data'])) {
              $element[$target_row_key][$target_col_key]['color'] = $element[$target_row_key][$target_col_key]['data'];
              $element[$target_row_key][$target_col_key]['color']['#type'] = 'textfield';
              $element[$target_row_key][$target_col_key]['color']['#attributes']['TYPE'] = 'color';
              $element[$target_row_key][$target_col_key]['color']['#attributes']['style'] = 'min-width:50px;';
              $element[$target_row_key][$target_col_key]['color']['#maxlength'] = 7;
              unset($element[$target_row_key][$target_col_key]['color']['#groups']);
              // Update identity for the color sub-element.
              $this->updateSubElementIdentity($element[$target_row_key][$target_col_key]['color'], 'data', 'color');
            }
            if (isset($element[$target_row_key][$target_col_key]['color'])) {
              $color_value = $cell_data['color'] instanceof TranslatableMarkup ? $cell_data['color']->__toString() : $cell_data['color'];
              $element[$target_row_key][$target_col_key]['color']['#value'] = $color_value;
              $element[$target_row_key][$target_col_key]['color']['#default_value'] = $color_value;
            }
          }
        }

        // Move 'delete' button to the end of the row so it stays in the
        // last column after any newly added data columns.
        if (isset($element[$target_row_key]['delete'])) {
          $delete = $element[$target_row_key]['delete'];
          unset($element[$target_row_key]['delete']);
          $element[$target_row_key]['delete'] = $delete;
        }
      }
    }
  }

  /**
   * Clone a cell element and update its identity for a new column index.
   */
  protected function cloneCellElement(array $source_cell, $source_index, $target_index): array {
    $cell = $source_cell;
    foreach (['data', 'color'] as $type) {
      if (!isset($cell[$type])) {
        continue;
      }
      // Update #parents: the column index is second-to-last.
      if (isset($cell[$type]['#parents'])) {
        $parents = $cell[$type]['#parents'];
        $parents[count($parents) - 2] = $target_index;
        $cell[$type]['#parents'] = $parents;
      }
      // Rebuild #name from #parents.
      if (isset($cell[$type]['#parents'])) {
        $parents = $cell[$type]['#parents'];
        $first = array_shift($parents);
        $cell[$type]['#name'] = $first . '[' . implode('][', $parents) . ']';
      }
      // Update #id: the column index is second-to-last segment.
      if (isset($cell[$type]['#id'])) {
        $id_parts = explode('-', $cell[$type]['#id']);
        $id_parts[count($id_parts) - 2] = (string) $target_index;
        $cell[$type]['#id'] = implode('-', $id_parts);
      }
      if (isset($cell[$type]['#attributes']['id'])) {
        $cell[$type]['#attributes']['id'] = $cell[$type]['#id'];
      }
      // Update #array_parents if present.
      if (isset($cell[$type]['#array_parents'])) {
        $array_parents = $cell[$type]['#array_parents'];
        $array_parents[count($array_parents) - 2] = (string) $target_index;
        $cell[$type]['#array_parents'] = $array_parents;
      }
      // Clear values and stale form processing data.
      unset(
        $cell[$type]['#value'],
        $cell[$type]['#default_value'],
        $cell[$type]['#groups'],
        $cell[$type]['#needs_validation'],
        $cell[$type]['#errors']
      );
    }
    // Update the cell container's identity too.
    if (isset($cell['#parents'])) {
      $parents = $cell['#parents'];
      $parents[count($parents) - 1] = $target_index;
      $cell['#parents'] = $parents;
    }
    if (isset($cell['#id'])) {
      $id_parts = explode('-', $cell['#id']);
      $id_parts[count($id_parts) - 1] = (string) $target_index;
      $cell['#id'] = implode('-', $id_parts);
    }
    if (isset($cell['#array_parents'])) {
      $array_parents = $cell['#array_parents'];
      $array_parents[count($array_parents) - 1] = (string) $target_index;
      $cell['#array_parents'] = $array_parents;
    }
    return $cell;
  }

  /**
   * Update identity properties when changing a sub-element type.
   */
  protected function updateSubElementIdentity(array &$sub_element, string $old_type, string $new_type): void {
    if (isset($sub_element['#parents'])) {
      $parents = $sub_element['#parents'];
      $parents[count($parents) - 1] = $new_type;
      $sub_element['#parents'] = $parents;
    }
    if (isset($sub_element['#parents'])) {
      $parents = $sub_element['#parents'];
      $first = array_shift($parents);
      $sub_element['#name'] = $first . '[' . implode('][', $parents) . ']';
    }
    if (isset($sub_element['#id'])) {
      $id_parts = explode('-', $sub_element['#id']);
      $id_parts[count($id_parts) - 1] = $new_type;
      $sub_element['#id'] = implode('-', $id_parts);
    }
    if (isset($sub_element['#attributes']['id'])) {
      $sub_element['#attributes']['id'] = $sub_element['#id'];
    }
    if (isset($sub_element['#array_parents'])) {
      $array_parents = $sub_element['#array_parents'];
      $array_parents[count($array_parents) - 1] = $new_type;
      $sub_element['#array_parents'] = $array_parents;
    }
  }

}
