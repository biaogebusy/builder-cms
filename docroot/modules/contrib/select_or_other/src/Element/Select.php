<?php

namespace Drupal\select_or_other\Element;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form element with a select box and other option.
 *
 * @see ElementBase
 *
 * @FormElement("select_or_other_select")
 */
class Select extends ElementBase {

  /**
   * {@inheritdoc}
   */
  public static function processSelectOrOther(&$element, FormStateInterface $form_state, &$complete_form) {
    $element = parent::processSelectOrOther($element, $form_state, $complete_form);

    static::setSelectType($element);
    static::addEmptyOption($element);
    static::addStatesHandling($element);
    static::addAjaxHandling($element);

    return $element;
  }

  /**
   * Sets the type of buttons to use for the select element.
   *
   * @param array $element
   *   The select or other element.
   */
  protected static function setSelectType(array &$element) {
    $element['select']['#type'] = 'select';
  }

  /**
   * Adds an empty option to the select element if required.
   *
   * @param array $element
   *   The select or other element.
   */
  protected static function addEmptyOption(array &$element) {
    if (!isset($element['#no_empty_option']) || !$element['#no_empty_option']) {
      if (!$element['#required'] || empty($element['#default_value'])) {
        $element['select']['#empty_value'] = '';
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
      $element['select']['#multiple'] = TRUE;

      // @todo Drupal #states does not support multiple select elements. We have
      // to simulate #states using our own javascript until #1149078 is
      // resolved. @see https://www.drupal.org/node/1149078
      $element['select']['#attached'] = [
        'library' => ['select_or_other/multiple_select_states_hack'],
      ];
    }
  }

  /**
   * Adds a #ajax array to select field to make Form API ajax callbacks work.
   *
   * @param array $element
   *   The select or other element.
   */
  protected static function addAjaxHandling(array &$element) {
    if (isset($element['#ajax'])) {
      $element['select']['#ajax'] = $element['#ajax'];
    }
  }

}
