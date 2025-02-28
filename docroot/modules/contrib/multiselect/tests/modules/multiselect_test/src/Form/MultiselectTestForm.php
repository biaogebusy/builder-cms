<?php

namespace Drupal\multiselect_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides test pages for testing the Multiselect module.
 */
class MultiselectTestForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'multiselect_test';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $elements = [];
    $elements['no_default'] = [
      '#title' => 'Multiselect without a default value',
    ];
    $elements['single_default'] = [
      '#title' => 'Multiselect with a default value',
      '#default_value' => 'banana',
    ];
    $elements['multiple_default'] = [
      '#title' => 'Multiselect with multiple default values',
      '#default_value' => ['banana', 'cherry'],
    ];

    $options = [
      'apple' => 'Apple',
      'banana' => 'Banana',
      'cherry' => 'Cherry',
    ];
    foreach ($elements as $key => $element) {
      // Wrap each multiselect in a fieldset.
      $form[$key . '_wrapper'] = [
        '#type' => 'fieldset',
        '#title' => $element['#title'] . ' wrapper',
      ];
      $form[$key . '_wrapper'][$key] = $element + [
        '#type' => 'multiselect',
        '#options' => $options,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
