<?php

namespace Drupal\select_or_other\Element;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form element with buttons and other option.
 *
 * @see ElementBase
 *
 * @FormElement("select_or_other_buttons")
 */
class Buttons extends ElementBase {

  /**
   * {@inheritdoc}
   */
  public static function processSelectOrOther(&$element, FormStateInterface $form_state, &$complete_form) {
    $element = parent::processSelectOrOther($element, $form_state, $complete_form);

    static::setSelectType($element);
    static::addStatesHandling($element);
    static::addEmptyOption($element);

    return $element;
  }

  /**
   * Sets the type of buttons to use for the select element.
   *
   * @param array $element
   *   The select or other element.
   */
  protected static function setSelectType(array &$element) {
    if ($element['#multiple']) {
      $element['select']['#type'] = 'checkboxes';
    }
    else {
      $element['select']['#type'] = 'radios';
      static::ensureCorrectDefaultValue($element);
    }
  }

  /**
   * Ensures the element has the correct default value.
   *
   * @param array $element
   *   The select or other element.
   */
  protected static function ensureCorrectDefaultValue(array &$element) {
    if ($element['select']['#type'] === 'radios') {
      // Radio buttons do not accept an array as default value.
      if (!empty($element['select']['#default_value']) && is_array($element['select']['#default_value'])) {
        $element['select']['#default_value'] = reset($element['select']['#default_value']);
      }
    }
  }

  /**
   * Adds a #states array to the other field to make hide/show work.
   *
   * @param array $element
   *   The select or other element.
   */
  protected static function addStatesHandling(array &$element) {
    if (!$element['#multiple']) {
      $element['other']['#states'] = static::prepareState('visible', $element['#name'] . '[select]', 'value', 'select_or_other');
    }
    else {
      $element['other']['#states'] = static::prepareState('visible', $element['#name'] . '[select][select_or_other]', 'checked', TRUE);
    }
  }

  /**
   * Adds an empty option to the select element if required.
   *
   * @param array $element
   *   The select or other element.
   */
  protected static function addEmptyOption(array &$element) {
    if ((!isset($element['#no_empty_option']) || !$element['#no_empty_option']) && !$element['#multiple']) {
      if (!$element['#required'] && !empty($element['#default_value'])) {
        $element['select']['#options'] = ['' => t('- None -')] + $element['select']['#options'];
      }
      else {
        $element['select']['#value'] = [];
      }
    }
  }

}
