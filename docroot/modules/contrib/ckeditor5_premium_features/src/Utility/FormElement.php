<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Utility;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides the common form elements that may be reused among the features.
 */
class FormElement {

  /**
   * Adds the format select field to the element.
   *
   * @param array $element
   *   The form or form element to which the format
   *   should be added.
   * @param array $options
   *   The additional options to merged into element.
   */
  public static function format(array &$element, array $options = []): void {
    $element['format'] = $options + [
      '#type' => 'select',
      '#title' => new TranslatableMarkup('Page format'),
      '#options' => [
        'Letter' => new TranslatableMarkup('Letter'),
        'Legal' => new TranslatableMarkup('Legal'),
        'Tabloid' => new TranslatableMarkup('Tabloid'),
        'Statement' => new TranslatableMarkup('Statement'),
        'Executive' => new TranslatableMarkup('Executive'),
        'A3' => new TranslatableMarkup('A3'),
        'A4' => new TranslatableMarkup('A4'),
        'A5' => new TranslatableMarkup('A5'),
        'A6' => new TranslatableMarkup('A6'),
        'B4' => new TranslatableMarkup('B4'),
        'B5' => new TranslatableMarkup('B5'),
      ],
      '#ajax' => FALSE,
    ];
  }

  /**
   * Adds the page orientation select field to the element.
   *
   * @param array $element
   *   The form or form element to which the page orientation
   *   should be added.
   * @param array $options
   *   The additional options to merged into element.
   */
  public static function pageOrientation(array &$element, array $options = []): void {
    $element['page_orientation'] = $options + [
      '#type' => 'select',
      '#title' => new TranslatableMarkup('Page orientation'),
      '#options' => [
        'portrait' => new TranslatableMarkup('Portrait'),
        'landscape' => new TranslatableMarkup('Landscape'),
      ],
      '#ajax' => FALSE,
    ];
  }

  /**
   * Adds the footer or header to the element.
   *
   * @param array $element
   *   The form or form element to which the format
   *   should be added.
   * @param string $type
   *   The type: footer or header.
   * @param array $options
   *   The additional options to merged into element.
   * @param int $items_length
   *   Number of items to add.
   */
  public static function headingFooter(array &$element, string $type = 'header', array $options = [], int $items_length = 1): void {
    $actions = [
      '#type' => 'container',
    ];

    $selector = $type . '-items-wrapper';
    $fieldset = [
      '#type' => 'fieldset',
      // phpcs:ignore
      '#title' => new TranslatableMarkup(ucfirst($type) . 's'),
      '#tree' => TRUE,
      '#id' => $selector,
    ];

    for ($index = 0; $index < $items_length; $index++) {
      $fieldset[$index]['html'] = [
        '#type' => 'textarea',
        '#title' => 'HTML',
        '#default_value' => $options[$index]['html'] ?? NULL,
        '#prefix' => $index > 0 ? '<br />' : '',
        '#ajax' => FALSE,
      ];

      $fieldset[$index]['css'] = [
        '#type' => 'textarea',
        '#title' => 'CSS',
        '#default_value' => $options[$index]['css'] ?? NULL,
        '#ajax' => FALSE,
      ];

      $fieldset[$index]['type'] = [
        '#type' => 'select',
        '#title' => new TranslatableMarkup('Type'),
        '#default_value' => $options[$index]['type'] ?? NULL,
        '#options' => [
          'default' => new TranslatableMarkup('Default'),
          'even' => new TranslatableMarkup('Even'),
          'odd' => new TranslatableMarkup('Odd'),
          'first' => new TranslatableMarkup('First'),
        ],
        '#ajax' => FALSE,
      ];
    }

    $actions['add_' . $type] = [
      '#type' => 'submit',
      '#value' => new TranslatableMarkup('Add one more @type', [
        '@type' => $type,
      ]),
      '#submit' => [
        [
          static::class,
          $type . 'AddOne',
        ],
      ],
      '#ajax' => [
        'callback' => [
          static::class,
          $type . 'AddMoreWordCallback',
        ],
        'wrapper' => $selector,
      ],
    ];

    if ($items_length > 1) {
      $actions['remove_' . $type] = [
        '#type' => 'submit',
        '#value' => new TranslatableMarkup('Remove one @type', [
          '@type' => $type,
        ]),
        '#submit' => [
          [
            static::class,
            $type . 'RemoveCallback',
          ],
        ],
        '#ajax' => [
          'callback' => [
            static::class,
            $type . 'AddMoreWordCallback',
          ],
          'wrapper' => $selector,
        ],
      ];
    }
    $fieldset['actions'] = $actions;
    $element[$type] = $fieldset;
  }

  /**
   * Adds margin fields to the form element.
   *
   * @param array $element
   *   Form element.
   * @param string $type
   *   Type of margin field to be added.
   * @param array|string|null $margin_config
   *   Current margin configuration values.
   */
  public static function marginElement(array &$element, string $type, array|string|null $margin_config = NULL): void {
    $matching_keys = [];
    if (!is_array($margin_config)) {
      if (preg_match('/(\d+)([^\s]+)/', $margin_config ?? '', $matching_keys)) {
        $margin_config = [
          'value' => $matching_keys[1],
          'units' => $matching_keys[2],
        ];
      }
      else {
        $margin_config = [
          'value' => '1',
          'units' => 'cm',
        ];
      }
    }

    $element['margin_' . $type] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'form-item',
        ],
        'style' => 'display: flex; align-items: end;',
      ],
      'value' => [
        '#type' => 'number',
        '#title' => t("Margin @type", [
          '@type' => $type,
        ]),
        '#default_value' => $margin_config['value'] ?? '1cm',
        '#min' => 0,
        '#wrapper_attributes' => [
          'style' => 'margin-top: 0; margin-bottom: 0;',
        ],
      ],
      'units' => [
        '#type' => 'select',
        '#default_value' => $margin_config['units'] ?? 'cm',
        '#options' => [
          'in' => 'inches',
          'cm' => 'centimeters',
          'mm' => 'millimeters',
          'px' => 'pixels',
        ],
        '#wrapper_attributes' => [
          'style' => 'margin-top: 0; margin-bottom: 0;',
        ],
        '#ajax' => FALSE,
      ],
    ];
  }

  /**
   * Sets element placeholders if corresponding key is found in $placeholders.
   *
   * @param array $form
   *   Form to be processed.
   * @param array $placeholders
   *   Array containing placeholders to be used.
   */
  public static function setPlaceholders(array &$form, array $placeholders): void {
    foreach ($form as $name => &$item) {
      if (!is_array($item)) {
        continue;
      }
      if (array_key_exists('#default_value', $item)) {
        if (isset($placeholders[$name])) {
          $item['#attributes']['placeholder'] = $placeholders[$name];
        }
      }
      else {
        static::setPlaceholders($item, $placeholders);
      }
    }
  }

  /**
   * Walks through the form and disables all fields and buttons.
   *
   * @param array $form
   *   Form to be processed.
   */
  public static function disableFormFields(array &$form): void {
    foreach ($form as $key => &$item) {
      if (!is_array($item) || $key == 'override_global') {
        continue;
      }
      if (array_key_exists('#default_value', $item) || isset($item['#type']) && $item['#type'] == 'submit') {
        $item['#disabled'] = TRUE;
      }
      else {
        static::disableFormFields($item);
      }
    }
  }

  /**
   * Callback for export to Word header buttons (add/remove).
   */
  public static function headerAddMoreWordCallback(array &$form, FormStateInterface $form_state): ?array {
    $settings_element = $form['editor']['settings']['subform']['plugins']['ckeditor5_premium_features_export_word__export_word'] ?? $form;

    return $settings_element['converter_options']['header'] ?? NULL;
  }

  /**
   * Callback for export to Word footer buttons (add/remove).
   */
  public static function footerAddMoreWordCallback(array &$form, FormStateInterface $form_state): ?array {
    $settings_element = $form['editor']['settings']['subform']['plugins']['ckeditor5_premium_features_export_word__export_word'] ?? $form;

    return $settings_element['converter_options']['footer'] ?? NULL;
  }

  /**
   * Submit handler for the header "add-one-more" button.
   */
  public static function headerAddOne(array &$form, FormStateInterface $form_state): void {
    static::modifyItemCounter($form_state, 'num_headers', 1);
  }

  /**
   * Submit handler for the footer "add-one-more" button.
   */
  public static function footerAddOne(array &$form, FormStateInterface $form_state) : void {
    static::modifyItemCounter($form_state, 'num_footers', 1);
  }

  /**
   * Submit handler for the header "remove-one" button.
   */
  public static function headerRemoveCallback(array &$form, FormStateInterface $form_state): void {
    static::modifyItemCounter($form_state, 'num_headers');
  }

  /**
   * Submit handler for the footer "remove-one" button.
   */
  public static function footerRemoveCallback(array &$form, FormStateInterface $form_state): void {
    static::modifyItemCounter($form_state, 'num_footers');
  }

  /**
   * Increments or decrements the items max counter and causes a rebuild.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   * @param string $counter_name
   *   Name of property to modify.
   * @param int $modifier
   *   Value to modify the counter.
   */
  public static function modifyItemCounter(FormStateInterface $form_state, string $counter_name, int $modifier = -1): void {
    $name_field = $form_state->get($counter_name);
    if ($modifier > 0 || $name_field > abs($modifier)) {
      $remove_button = $name_field + $modifier;
      $form_state->set($counter_name, $remove_button);
    }

    $form_state->setRebuild();
  }

}
