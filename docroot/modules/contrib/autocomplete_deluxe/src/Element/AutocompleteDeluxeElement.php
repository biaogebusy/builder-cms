<?php

namespace Drupal\autocomplete_deluxe\Element;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\CompositeFormElementTrait;
use Drupal\Core\Render\Element\FormElement;

/**
 * Provides an Autocomplete Deluxe Form API element.
 *
 * @FormElement("autocomplete_deluxe")
 */
class AutocompleteDeluxeElement extends FormElement {

  use CompositeFormElementTrait;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);

    // Apply default form element properties.
    $info['#target_type'] = NULL;
    $info['#selection_handler'] = 'default';
    $info['#selection_settings'] = [];
    $info['#tags'] = TRUE;
    $info['#autocreate'] = NULL;
    // This should only be set to FALSE if proper validation by the selection
    // handler is performed at another level on the extracted form values.
    $info['#validate_reference'] = TRUE;
    // IMPORTANT! This should only be set to FALSE if the #default_value
    // property is processed at another level (e.g. by a Field API widget) and
    // its value is properly checked for access.
    $info['#process_default_value'] = TRUE;

    $info['#element_validate'] = [['\Drupal\Core\Entity\Element\EntityAutocomplete',
      'validateEntityAutocomplete',
    ],
    ];
    $info['#process'][] = [$class, 'processElement'];

    return $info;
  }

  /**
   * Autocomplete Deluxe element process callback.
   */
  public static function processElement($element) {
    // Do not attach js library if the element is disabled.
    $element_disabled = $element['#disabled'] ?? FALSE;
    if (!$element_disabled) {
      $element['#attached']['library'][] = 'autocomplete_deluxe/assets';

      $active_theme = \Drupal::theme()->getActiveTheme();
      $base_themes = (array) $active_theme->getBaseThemeExtensions();

      if ($active_theme->getName() === 'gin'|| array_key_exists('gin', $base_themes)) {
        // Workaround for problems with jquery css in claro theme.
        $element['#attached']['library'][] = 'autocomplete_deluxe/assets.claro';
        // Overrides to support Gin's CSS3 variables for Darkmode, Accent etc.
        $element['#attached']['library'][] = 'autocomplete_deluxe/assets.gin';
      }
      elseif ($active_theme->getName() === 'claro'|| array_key_exists('claro', $base_themes)) {
        // Workaround for problems with jquery css in claro theme.
        $element['#attached']['library'][] = 'autocomplete_deluxe/assets.claro';
      }
      elseif ($active_theme->getName() == 'seven'|| array_key_exists('seven', $base_themes)) {
        // Workaround for problems with jquery css in seven theme.
        $element['#attached']['library'][] = 'autocomplete_deluxe/assets.seven';
      }
    }

    $html_id = Html::getUniqueId('autocomplete-deluxe-input');

    $element['#after_build'][] = [get_called_class(), 'afterBuild'];

    // Set default options for multiple values.
    $element['#multiple'] = $element['#multiple'] ?? FALSE;

    // Add label_display and label variables to template.
    $element['label'] = ['#theme' => 'form_element_label'];
    $element['label'] += array_intersect_key(
      $element,
      array_flip(
        [
          '#id',
          '#required',
          '#title',
          '#title_display',
        ]
      )
    );

    $element['textfield'] = [
      '#disabled' => $element_disabled,
      '#type' => 'textfield',
      '#size' => $element['#size'] ?? '',
      '#attributes' => [
        'class' => ['autocomplete-deluxe-form'],
        'id' => $html_id,
      ],
      '#default_value' => '',
      '#description' => $element['#description'] ?? '',
    ];

    // Add autcomplete deluxe container only if element is enabled.
    if (!$element_disabled) {
      $element['textfield']['#prefix'] = '<div class="autocomplete-deluxe-container">';
      $element['textfield']['#suffix'] = '</div>';
    }

    $js_settings[$html_id] = [
      'input_id' => $html_id,
      'multiple' => $element['#multiple'],
      'required' => $element['#required'],
      'limit' => $element['#limit'] ?? 10,
      'min_length' => $element['#min_length'] ?? 0,
      'use_synonyms' => $element['#use_synonyms'] ?? 0,
      'delimiter' => $element['#delimiter'] ?? '',
      'not_found_message_allow' => $element['#not_found_message_allow'] ?? FALSE,
      'not_found_message' => $element['#not_found_message'] ?? "The term '@term' will be added.",
      'new_terms' => $element['#new_terms'] ?? FALSE,
      'no_empty_message' => $element['#no_empty_message'] ?? 'No terms could be found. Please type in order to add a new term.',
    ];

    if (isset($element['#autocomplete_deluxe_path'])) {
      if (isset($element['#default_value'])) {
        // Split on the comma only if that comma has zero, or an even number of
        // quotes in ahead of it.
        // http://stackoverflow.com/questions/1757065/java-splitting-a-comma-separated-string-but-ignoring-commas-in-quotes
        $default_value = preg_replace('/,(?=([^\"]*\"[^\"]*\")*[^\"]*$)/i', '"" ""', $element['#default_value']);
        $default_value = '""' . $default_value . '""';
      }
      else {
        $default_value = '';
      }

      if ($element['#multiple']) {
        $element['value_field'] = [
          '#type' => 'textfield',
          '#attributes' => [
            'class' => ['autocomplete-deluxe-value-field'],
          ],
          '#default_value' => $default_value,
          '#prefix' => '<div class="autocomplete-deluxe-value-container">',
          '#suffix' => '</div>',
          '#description' => $element['#description'] ?? '',
        ];
        $element['textfield']['#attributes']['style'] = ['display: none'];
      }
      else {
        $element['textfield']['#default_value'] = $element['#default_value'] ?? '';
      }

      $js_settings[$html_id] += [
        'type' => 'ajax',
        'uri' => $element['#autocomplete_deluxe_path'],
      ];
    }
    else {
      // If there is no source (path or data), we don't want to add the js
      // settings and so the functions will be aborted.
      return $element;
    }

    // Do not attach js settings if element is disabled.
    if (!$element_disabled) {
      $element['#attached']['drupalSettings']['autocomplete_deluxe'] = $js_settings;
    }
    $element['#tree'] = TRUE;

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    return [];
  }

  /**
   * Form API after build callback for the duration parameter type form.
   *
   * Fixes up the form value by applying the multiplier.
   */
  public static function afterBuild(array $element, FormStateInterface $form_state) {
    // By default Drupal sets the maxlength to 128 if the property isn't
    // specified, but since the limit isn't useful in some cases,
    // we unset the property.
    unset($element['textfield']['#maxlength']);

    // Set the elements value from either the value field or text field input.
    $element['#value'] = isset($element['value_field']) ? $element['value_field']['#value'] : $element['textfield']['#value'];

    if (isset($element['value_field'])) {
      $element['#value'] = trim($element['#value']);
      // Replace all cases of double double quotes and one or more spaces with a
      // comma. This will allow us to keep entries in double quotes.
      $element['#value'] = preg_replace('/"" +""/', ',', $element['#value']);
      // Remove the double quotes at the beginning and the end from the first
      // and the last term.
      $element['#value'] = substr($element['#value'], 2, strlen($element['#value']) - 4);

      unset($element['value_field']['#maxlength']);
    }

    $form_state->setValueForElement($element, $element['#value']);

    return $element;
  }

}
