<?php

namespace Drupal\select_or_other\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;

/**
 * Base class for select or other form elements.
 *
 * Properties:
 * - #multiple: If the widget should allow multiple values to be selected.
 * - #select_type: Either 'list' for a select list and 'buttons' for checkboxes
 *   or radio buttons depending on cardinality.
 * - #merged_values: Set this to true if the widget should return a single array
 *   with the merged values from both the 'select' and 'other' fields.
 * - #options: An associative array, where the keys are the retured values for
 *   each option, and the values are the options to be presented to the user.
 * - #empty_option: The label that will be displayed to denote no selection.
 * - #empty_value: The value of the option that is used to denote no selection.
 * - #input_type: The element type to be used in the 'other' field.
 */
abstract class ElementBase extends FormElement {

  /**
   * Adds an '- Other -' option to the selectbox.
   */
  protected static function addOtherOption($options, $other_option = '') {
    if (empty($other_option)) {
      $other_option = t('- Other -');
    }
    $options['select_or_other'] = $other_option;

    return $options;
  }

  /**
   * Prepares an array to be used as a state in a form API #states array.
   *
   * @param string $state
   *   The state the element should have.
   * @param string $element_name
   *   The name of the element on which this state depends.
   * @param string $value_key
   *   The key used to select the property on which the state depends.
   * @param mixed $value
   *   The value a property should have to trigger the state.
   *
   * @return array
   *   An array with state information to be used in a #states array.
   */
  protected static function prepareState($state, $element_name, $value_key, $value) {
    return [
      $state => [
        ':input[name="' . $element_name . '"]' => [$value_key => $value],
      ],
    ];
  }

  /**
   * Check whether or not the element is disabled.
   *
   * @param array $element
   *   The element to check for enabled state.
   *
   * @return bool
   *   Whether or not the element is disabled.
   */
  private static function elementIsDisabled(array $element) {
    return isset($element['#disabled']) && $element['#disabled'];
  }

  /**
   * Check whether or not the element may be accessed.
   *
   * @param array $element
   *   The element to check for access.
   *
   * @return bool
   *   Whether or not the element may be accessed.
   */
  private static function noElementAccess(array $element) {
    return isset($element['#access']) && !$element['#access'];
  }

  /**
   * {@inheritdoc}
   *
   * @codeCoverageIgnore
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#process' => [[$class, 'processSelectOrOther']],
      '#multiple' => FALSE,
      '#select_type' => 'list',
      '#merged_values' => FALSE,
      '#theme_wrappers' => ['form_element'],
      '#options' => [],
      '#tree' => TRUE,
      '#input_type' => 'textfield',
    ];
  }

  /**
   * Render API callback: Expands the select_or_other element type.
   *
   * Expands the select or other element to have a 'select' and 'other' field.
   */
  public static function processSelectOrOther(&$element, FormStateInterface $form_state, &$complete_form) {
    static::addSelectField($element);
    static::addOtherField($element);
    return $element;
  }

  /**
   * Adds the 'select' field to the element.
   *
   * @param array $element
   *   The select or other element.
   */
  protected static function addSelectField(array &$element) {
    if (!empty($element['#other_options'])) {
      // Add "Other" to default values if "Other" was selected.
      $element['#default_value'][] = "select_or_other";
    }

    $element['select'] = [
      '#default_value' => $element['#default_value'],
      '#required' => $element['#required'],
      '#multiple' => $element['#multiple'],
      '#options' => $element['#original_options'] ?? $element['#options'],
      '#attributes' => [
        'aria-label' => $element['#title'] ?? $element['#name'],
      ],
      '#weight' => 10,
    ];

    if ($element['#other_allowed'] ?? TRUE) {
      $element['select']['#options'] = static::addOtherOption($element['select']['#options'], $element['#other_option']);
    }
  }

  /**
   * Adds the 'other' field to the element.
   *
   * @param array $element
   *   The select or other element.
   */
  protected static function addOtherField(array &$element) {
    if ($element['#other_allowed'] ?? TRUE) {
      $element['other'] = [
        '#type' => $element['#input_type'] ?? 'textfield',
        '#attributes' => [
          'aria-label' => isset($element['#title']) ? $element['#title'] . ' Other' : $element['#name'] . ' Other',
        ],
        '#weight' => 20,
      ];
    }

    if (isset($element['#other_field_label']) && !empty($element['#other_field_label'])) {
      $element['other']['#title'] = $element['#other_field_label'];
      $element['other']['#attributes']['aria-label'] = $element['#other_field_label'];
    }

    if (isset($element['#other_options'])) {
      $element['other']['#default_value'] = $element['#other_options'];
    }

    if (isset($element['#other_placeholder'])) {
      $element['other']['#attributes']['placeholder'] = $element['#other_placeholder'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if (self::elementIsDisabled($element) || self::noElementAccess($element)) {
      unset($element['#value']);
      return NULL;
    }

    $values = [];
    if ($input !== FALSE && !empty($input['select'])) {

      if ($element['#multiple']) {
        $values = [
          'select' => (array) $input['select'],
          'other' => !empty($input['other']) ? (array) $input['other'] : [],
        ];

        if (in_array('select_or_other', $values['select'])) {
          $values['select'] = array_diff($values['select'], ['select_or_other']);
        }
        else {
          $values['other'] = [];
        }

        if (isset($element['#merged_values']) && $element['#merged_values']) {
          if (!empty($values['other'])) {
            if (is_array($values['select']) && array_key_exists('select_or_other', $values['select'])) {
              $select = array_pop($values['select']) ?? [];
              $values = array_values(array_merge($select, $values['other']));
            }
            else {
              $values = array_values(array_merge($values['select'], $values['other']));
            }
            // Add the other option to the available options to prevent
            // validation errors.
            $element['#options'][$input['other']] = $input['other'];
          }
          else {
            $select = array_filter($values['select']);
            $values = array_values($select);
          }
        }

      }
      else {
        if ($input['select'] === 'select_or_other') {
          $values = [$input['other']];
          // Add the other option to the available options to prevent
          // validation errors.
          $element['#options'][$input['other']] = $input['other'];
        }
        else {
          $values = [$input['select']];
        }
      }
    }

    return $values;
  }

}
