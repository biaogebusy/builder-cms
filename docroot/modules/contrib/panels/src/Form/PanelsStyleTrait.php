<?php

namespace Drupal\panels\Form;

/**
 * Style-related helper functions.
 */
trait PanelsStyleTrait {

  /**
   * Get CSS style related form.
   *
   * @param array $default_value
   *   Form items default value.
   * @param bool $tree
   *   Form item nested settings, see Form API reference.
   *
   * @return array
   *   The form array.
   */
  public function getCssStyleForm(array $default_value, $tree = FALSE) {
    $form = [];
    $form['style_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Style settings'),
      '#open' => FALSE,
      '#tree' => $tree,
    ];
    $form['style_settings']['css_classes'] = [
      '#title' => $this->t('CSS classes'),
      '#type' => 'textfield',
      '#default_value' => !empty($default_value['css_classes']) ? implode(' ', $default_value['css_classes']) : NULL,
      '#description' => $this->t('Customize the element style by adding CSS classes. Separate multiple classes by spaces.'),
    ];
    $form['style_settings']['html_id'] = [
      '#title' => $this->t('HTML Id.'),
      '#type' => 'textfield',
      '#default_value' => !empty($default_value['html_id']) ? $default_value['html_id'] : NULL,
      '#description' => $this->t('Customize the element style by adding CSS #id.'),
    ];
    $form['style_settings']['css_styles'] = [
      '#title' => $this->t('CSS styles'),
      '#type' => 'textarea',
      '#default_value' => !empty($default_value['css_styles']) ? $default_value['css_styles'] : NULL,
      '#description' => $this->t('Customize the element style by adding inline CSS.'),
    ];
    return $form;
  }

}