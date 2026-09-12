<?php

namespace Drupal\webform_jqueryui_datepicker\Hook;

use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformElement\Date;
use Drupal\webform\Plugin\WebformElement\DateBase;
use Drupal\webform\Plugin\WebformElement\DateList;
use Drupal\webform\Plugin\WebformElement\DateTime;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform_jqueryui_datepicker.
 */
class WebformJqueryuiDatepickerHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_webform_element_default_properties_alter().
   */
  #[Hook('webform_element_default_properties_alter')]
  public function webformElementDefaultPropertiesAlter(array &$properties, array &$definition) {
    /** @var \Drupal\webform\Plugin\WebformElementManagerInterface $element_manager */
    $element_manager = \Drupal::service('plugin.manager.webform.element');
    $element_plugin = $element_manager->createInstance($definition['id']);
    // Date element.
    if ($element_plugin instanceof Date) {
      $properties += ['datepicker' => FALSE, 'datepicker_button' => FALSE];
    }
    // Datetime element.
    if ($element_plugin instanceof DateTime) {
      $properties += ['date_date_datepicker_button' => FALSE];
    }
  }

  /**
   * Implements hook_webform_element_configuration_form_alter().
   */
  #[Hook('webform_element_configuration_form_alter')]
  public function webformElementConfigurationFormAlter(&$form, FormStateInterface $form_state) {
    /** @var \Drupal\webform_ui\Form\WebformUiElementEditForm $form_object */
    $form_object = $form_state->getFormObject();
    $element_plugin = $form_object->getWebformElementPlugin();
    // Date base element.
    if ($element_plugin instanceof DateBase) {
      $form['date']['date_days']['#description'] .= ' ' . $this->t('Please note, the date picker will disable unchecked days of the week.');
    }
    // Date element.
    if ($element_plugin instanceof Date) {
      $form['date']['datepicker'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Use date picker'),
        '#description' => $this->t('If checked, the HTML5 date element will be replaced with a <a href="https://jqueryui.com/datepicker/">jQuery UI datepicker</a>'),
        '#return_value' => TRUE,
      ];
      $form['date']['datepicker_button'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Show date picker button'),
        '#description' => $this->t('If checked, date picker will include a calendar button'),
        '#return_value' => TRUE,
        '#states' => [
          'visible' => [
            ':input[name="properties[datepicker]"]' => [
              'checked' => TRUE,
            ],
          ],
        ],
      ];
      $date_format = DateFormat::load('html_date')->getPattern();
      $form['date']['date_date_format'] = [
        '#type' => 'webform_select_other',
        '#title' => $this->t('Date format'),
        '#options' => [
          $date_format => $this->t('HTML date - @format (@date)', [
            '@format' => $date_format,
            '@date' => _webform_jqueryui_datepicker_format_date($date_format),
          ]),
          'l, F j, Y' => $this->t('Long date - @format (@date)', [
            '@format' => 'l, F j, Y',
            '@date' => _webform_jqueryui_datepicker_format_date('l, F j, Y'),
          ]),
          'D, m/d/Y' => $this->t('Medium date - @format (@date)', [
            '@format' => 'D, m/d/Y',
            '@date' => _webform_jqueryui_datepicker_format_date('D, m/d/Y'),
          ]),
          'm/d/Y' => $this->t('Short date - @format (@date)', [
            '@format' => 'm/d/Y',
            '@date' => _webform_jqueryui_datepicker_format_date('m/d/Y'),
          ]),
        ],
        '#description' => $this->t("Date format is only applicable for browsers that do not have support for the HTML5 date element. Browsers that support the HTML5 date element will display the date using the user's preferred format."),
        '#other__option_label' => $this->t('Custom…'),
        '#other__placeholder' => $this->t('Custom date format…'),
        '#other__description' => $this->t('Enter date format using <a href="http://php.net/manual/en/function.date.php">Date Input Format</a>.'),
        '#attributes' => [
          'data-webform-states-no-clear' => TRUE,
        ],
        '#states' => [
          'visible' => [
            ':input[name="properties[datepicker]"]' => [
              'checked' => TRUE,
            ],
          ],
        ],
      ];
      // Show placeholder for the datepicker only.
      $form['form']['placeholder']['#states'] = ['visible' => [':input[name="properties[datepicker]"]' => ['checked' => TRUE]]];
      $form['date']['date_container']['step'] = [
        '#type' => 'number',
        '#title' => $this->t('Step'),
        '#description' => $this->t('Specifies the legal number intervals.'),
        '#min' => 1,
        '#size' => 4,
        '#states' => [
          'invisible' => [
            ':input[name="properties[datepicker]"]' => [
              'checked' => TRUE,
            ],
          ],
        ],
      ];
    }
    // Datetime element.
    if ($element_plugin instanceof DateTime) {
      // Add datepicker option.
      $form['date']['date_date_element']['#options']['datepicker'] = $this->t('Date picker input - Use jQuery date picker with custom date format');
      // Move none options last.
      $none = $form['date']['date_date_element']['#options']['none'];
      unset($form['date']['date_date_element']['#options']['none']);
      $form['date']['date_date_element']['#options']['none'] = $none;
      // Add button support.
      $form['date']['date_date_datepicker_button'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Show date picker button'),
        '#description' => $this->t('If checked, date picker will include a calendar button'),
        '#return_value' => TRUE,
        '#states' => [
          'visible' => [
            ':input[name="properties[date_date_element]"]' => [
              'value' => 'datepicker',
            ],
          ],
        ],
      ];
      // Adjust weights.
      $form['date']['date_date_element']['#weight'] = -10;
      $form['date']['date_date_datepicker_button']['#weight'] = -9;
      // Adjust states.
      $form['date']['date_date_placeholder']['#states']['visible'] = [
            [
              ':input[name="properties[date_date_element]"]' => [
                'value' => 'text',
              ],
            ],
            'or',
            [
              ':input[name="properties[date_date_element]"]' => [
                'value' => 'datepicker',
              ],
            ],
      ];
      $form['date']['date_date_format']['#states']['visible'] = [
            [
              ':input[name="properties[date_date_element]"]' => [
                'value' => 'text',
              ],
            ],
            'or',
            [
              ':input[name="properties[date_date_element]"]' => [
                'value' => 'datepicker',
              ],
            ],
      ];
    }
  }

  /**
   * Implements hook_webform_element_alter().
   */
  #[Hook('webform_element_alter')]
  public function webformElementAlter(array &$element, FormStateInterface $form_state, array $context) {
    /** @var \Drupal\webform\Plugin\WebformElementManagerInterface $element_manager */
    $element_manager = \Drupal::service('plugin.manager.webform.element');
    $element_plugin = $element_manager->getElementInstance($element);
    // Date base element.
    if ($element_plugin instanceof DateBase) {
      // Display datepicker button.
      if (!empty($element['#datepicker_button']) || !empty($element['#date_date_datepicker_button'])) {
        $element['#attributes']['data-datepicker-button'] = TRUE;
        $button_image = base_path() . \Drupal::service('extension.list.module')->getPath('webform_jqueryui_datepicker') . '/images/elements/date-calendar.png';
        $element['#attached']['drupalSettings']['webform']['datePicker']['buttonImage'] = $button_image;
      }
    }
    // Date element.
    if ($element_plugin instanceof Date) {
      // Unset unsupported date format for date elements that are not using a
      // datepicker.
      if (empty($element['#datepicker'])) {
        unset($element['#date_date_format']);
      }
      // Convert date element into textfield with date picker.
      if (!empty($element['#datepicker'])) {
        $element['#attributes']['type'] = 'text';
        // Must manually set 'data-drupal-date-format' to trigger date picker.
        // @see \Drupal\Core\Render\Element\Date::processDate
        $element['#attributes']['data-drupal-date-format'] = [$element['#date_date_format']];
        $element['#attached']['library'][] = 'webform_jqueryui_datepicker/webform_jqueryui_datepicker.element';
      }
    }
    // DateTime element.
    if ($element_plugin instanceof DateTime && isset($element['#date_date_element']) && $element['#date_date_element'] === 'datepicker') {
      $element['#attached']['library'][] = 'webform_jqueryui_datepicker/webform_jqueryui_datepicker.element';
    }
    // DateList element.
    if ($element_plugin instanceof DateList) {
      // Unset unsupported datepicker and data format for date list elements.
      unset($element['#datepicker'], $element['#date_date_format']);
    }
  }

}
