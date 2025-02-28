<?php

namespace Drupal\image_effects\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\image_effects\Component\ColorUtility;

/**
 * Implements a form element to enable capturing color information.
 *
 * Enable capturing color information. Plugins allow to define alternative
 * color selectors.
 *
 * @FormElement("image_effects_color")
 */
class ImageEffectsColor extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#process' => [
        [$class, 'processImageEffectsColor'],
      ],
      '#element_validate' => [
        [$class, 'validateImageEffectsColor'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input !== FALSE && $input !== NULL) {
      // Make sure element properties are set.
      $element['#allow_null'] = isset($element['#allow_null']) ? $element['#allow_null'] : FALSE;
      $element['#allow_opacity'] = isset($element['#allow_opacity']) ? $element['#allow_opacity'] : FALSE;

      // Normalize returned element values to a RGBA hex value.
      $val = '';
      if ($element['#allow_null'] && !empty($input['container']['transparent'])) {
        return '';
      }
      elseif ($element['#allow_null'] || $element['#allow_opacity']) {
        $val = mb_strtoupper($input['container']['hex']);
      }
      else {
        $val = mb_strtoupper($input['hex']);
      }
      if ($val[0] <> '#') {
        $val = '#' . $val;
      }
      if ($element['#allow_opacity']) {
        $val .= ColorUtility::opacityToAlpha($input['container']['opacity']);
      }
      return $val;
    }
    return '';
  }

  /**
   * Processes a 'image_effects_color' form element.
   *
   * @param array $element
   *   The form element to process. Properties used:
   *     '#allow_null' - if set to TRUE, a checkbox is displayed to set the
   *      color as a full transparency, In this case, color hex and opacity are
   *      hidden, and the value returned is NULL.
   *     '#allow_opacity' - if set to TRUE, a textfield is displayed to capture
   *      the 'opacity' value, as a percentage.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element.
   */
  public static function processImageEffectsColor(array &$element, FormStateInterface $form_state, array &$complete_form) {
    // Make sure element properties are set.
    $element += [
      '#allow_null' => FALSE,
      '#allow_opacity' => FALSE,
      '#description' => NULL,
      '#states' => NULL,
      '#title' => t('Color'),
      '#checkbox_title' => t('Transparent'),
    ];

    // In case default value is transparent, set hex and opacity to default
    // values (white, fully opaque) so that if transparency is unchecked,
    // we have a starting value.
    $transparent = empty($element['#default_value']) ? TRUE : FALSE;
    $hex = $transparent ? '#FFFFFF' : mb_substr($element['#default_value'], 0, 7);
    $opacity = $transparent ? 100 : ColorUtility::rgbaToOpacity($element['#default_value']);

    $colorPlugin = \Drupal::service('plugin.manager.image_effects.color_selector')->getPlugin();

    if ($element['#allow_null'] || $element['#allow_opacity']) {
      // More sub-fields are needed to define the color, wrap them in a
      // container fieldset.
      $element['container'] = [
        '#type' => 'fieldset',
        '#description' => $element['#description'],
        '#title' => $element['#title'],
        '#states' => $element['#states'],
      ];
      // Checkbox for transparency.
      if ($element['#allow_null']) {
        $element['container']['transparent'] = [
          '#type' => 'checkbox',
          '#title' => $element['#checkbox_title'],
          '#default_value' => $transparent,
        ];
      }
      // Color field.
      $element['container']['hex'] = $colorPlugin->selectionElement(['#default_value' => $hex]);
      // States management for color field.
      $element['container']['hex']['#states'] = [
        'visible' => [
          ':input[name="' . $element['#name'] . '[container][transparent]"]' => ['checked' => FALSE],
        ],
      ];
      // Textfield for opacity.
      if ($element['#allow_opacity']) {
        $element['container']['opacity'] = [
          '#type'  => 'number',
          '#title' => t('Opacity'),
          '#default_value' => $opacity,
          '#maxlength' => 3,
          '#size' => 2,
          '#field_suffix' => '%',
          '#min' => 0,
          '#max' => 100,
          '#states' => [
            'visible' => [
              ':input[name="' . $element['#name'] . '[container][transparent]"]' => ['checked' => FALSE],
            ],
          ],
        ];
      }
    }
    else {
      // No transparency or opacity, straight color field.
      $options = $element;
      $options['#default_value'] = $hex;
      $element['hex'] = $colorPlugin->selectionElement($options);
    }

    unset(
      $element['#description'],
      $element['#title']
    );

    return $element;
  }

  /**
   * Form element validation handler.
   */
  public static function validateImageEffectsColor(&$element, FormStateInterface $form_state, &$complete_form) {
    $form_state->setValueForElement($element, $element['#value']);
  }

}
