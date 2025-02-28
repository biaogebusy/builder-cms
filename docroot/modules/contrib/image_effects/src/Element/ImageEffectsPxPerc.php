<?php

namespace Drupal\image_effects\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;

/**
 * Implements a form element for a quantity either in pixels or percentage.
 *
 * @FormElement("image_effects_px_perc")
 */
class ImageEffectsPxPerc extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#process' => [
        [$class, 'processImageEffectsPxPerc'],
      ],
      '#element_validate' => [
        [$class, 'validateImageEffectsPxPerc'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input !== FALSE && $input !== NULL) {
      if (empty($input['c0']['c1']['value'])) {
        return '';
      }
      if ($input['c0']['c1']['uom'] === 'perc') {
        return $input['c0']['c1']['value'] . '%';
      }
      else {
        return $input['c0']['c1']['value'];
      }
    }
    return '';
  }

  /**
   * Processes a 'image_effects_px_perc' form element.
   *
   * @param array $element
   *   The form element to process.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element.
   */
  public static function processImageEffectsPxPerc(array &$element, FormStateInterface $form_state, array &$complete_form) {
    // Make sure element properties are set.
    $element += [
      '#title' => NULL,
      '#description' => NULL,
      '#states' => NULL,
      '#size' => 4,
      '#maxlength' => 4,
    ];

    // Determine UoM and value.
    $default_value = $element['#default_value'] ?? '';
    if (strpos($default_value, '%') !== FALSE) {
      $uom = 'perc';
      $val = str_replace('%', '', $default_value);
    }
    else {
      $uom = 'px';
      $val = $default_value;
    }

    // Form elements.
    $element['c0'] = [
      '#type' => 'fieldset',
      '#title' => $element['#title'],
      '#attributes' => ['class' => ['fieldgroup', 'form-composite']],
      '#description' => $element['#description'],
    ];
    $element['c0']['c1'] = [
      '#type' => 'fieldset',
      '#attributes' => [
        'class' => [
          'container-inline',
          'fieldgroup',
          'form-composite',
        ],
      ],
    ];
    $element['c0']['c1']['value'] = [
      '#type' => 'number',
      '#default_value' => $val,
      '#size' => $element['#size'],
      '#maxlength' => $element['#maxlength'],
    ];
    $element['c0']['c1']['uom'] = [
      '#type' => 'radios',
      '#default_value' => $uom,
      '#options' => [
        'px' => t('px'),
        'perc' => t('%'),
      ],
    ];

    return $element;
  }

  /**
   * Form element validation handler.
   */
  public static function validateImageEffectsPxPerc(&$element, FormStateInterface $form_state, &$complete_form) {
    $form_state->setValueForElement($element, $element['#value']);
  }

}
