<?php

namespace Drupal\address_display\Plugin\Field\FieldFormatter;

use Drupal\address\LabelHelper;
use Drupal\address\Plugin\Field\FieldFormatter\AddressPlainFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'Address Display' formatter.
 *
 * @FieldFormatter(
 *   id = "address_display_formatter",
 *   label = @Translation("Address Display"),
 *   field_types = {
 *     "address"
 *   }
 * )
 */
class AddressDisplayFormatter extends AddressPlainFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = [
      'address_display' => [
        'organization' => [
          'display' => TRUE,
          'glue' => '',
          'weight' => -1,
        ],
        'address_line1' => [
          'display' => TRUE,
          'glue' => '',
          'weight' => 0,
        ],
        'address_line2' => [
          'display' => TRUE,
          'glue' => ',',
          'weight' => 1,
        ],
        'locality' => [
          'display' => TRUE,
          'glue' => ',',
          'weight' => 2,
        ],
        'postal_code' => [
          'display' => TRUE,
          'glue' => '',
          'weight' => 3,
        ],
        'country_code' => [
          'display' => TRUE,
          'glue' => '',
          'weight' => 4,
        ],
        'langcode' => [
          'display' => FALSE,
          'glue' => ',',
          'weight' => 100,
        ],
        'administrative_area' => [
          'display' => FALSE,
          'glue' => ',',
          'weight' => 100,
        ],
        'dependent_locality' => [
          'display' => FALSE,
          'glue' => ',',
          'weight' => 100,
        ],
        'sorting_code' => [
          'display' => FALSE,
          'glue' => ',',
          'weight' => 100,
        ],
        'given_name' => [
          'display' => TRUE,
          'glue' => '',
          'weight' => 100,
        ],
        'family_name' => [
          'display' => TRUE,
          'glue' => ',',
          'weight' => 100,
        ],
      ],
    ];

    return $settings + parent::defaultSettings();
  }

  /**
   * Helper function to get address components label.
   *
   * @return array
   */
  private function getLabels() {
    $values = LabelHelper::getGenericFieldLabels();

    return [
      'given_name' => $values['givenName'],
      'additional_name' => $values['additionalName'],
      'family_name' => $values['familyName'],
      'organization' => $values['organization'],
      'address_line1' => $values['addressLine1'],
      'address_line2' => $values['addressLine2'],
      'postal_code' => $values['postalCode'],
      'sorting_code' => $values['sortingCode'],
      'administrative_area' => $values['administrativeArea'],
      'locality' => $values['locality'],
      'dependent_locality' => $values['dependentLocality'],
      'country_code' => $this->t('Country code'),
      'langcode' => $this->t('Langcode'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {

    $form = parent::settingsForm($form, $form_state);
    $labels = $this->getLabels();

    $group_class = 'group-order-weight';
    $items = $this->getSetting('address_display');

    // Build table.
    $form['address_display'] = [
      '#type' => 'table',
      '#caption' => $this->t('Address display'),
      '#header' => [
        $this->t('Label'),
        $this->t('Display'),
        $this->t('Glue'),
        $this->t('Weight'),
      ],
      '#empty' => $this->t('No items.'),
      '#tableselect' => FALSE,
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => $group_class,
        ],
      ],
    ];

    // Build rows.
    foreach ($items as $key => $value) {
      $form['address_display'][$key]['#attributes']['class'][] = 'draggable';
      $form['address_display'][$key]['#weight'] = $value['weight'];

      // Label col.
      $form['address_display'][$key]['label'] = [
        '#plain_text' => $labels[$key],
      ];

      // ID col.
      $form['address_display'][$key]['display'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Display'),
        '#default_value' => $value['display'],
      ];

      // Glue col.
      $form['address_display'][$key]['glue'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Glue'),
        '#default_value' => $value['glue'],
      ];

      // Weight col.
      $form['address_display'][$key]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => $key]),
        '#title_display' => 'invisible',
        '#default_value' => $value['weight'],
        '#attributes' => ['class' => [$group_class]],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $config = $this->getSetting('address_display');
    $labels = $this->getLabels();
    $summary = [];
    $display = [];
    foreach ($config as $key => $config_item) {
      if ($config_item['display']) {
        $display[] = $labels[$key];
      }
    }

    if (!empty($display)) {
      $summary[] = $this->t('Display: @elements', ['@elements' => implode(', ', $display)]);
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $addressFormatRepository = \Drupal::service('address.address_format_repository');
      $address_format = $addressFormatRepository->get($item->getCountryCode());
      $format_values = $this->getValues($item, $address_format);
      $item = $item->toArray();
      $item['administrative_area'] = $format_values['administrativeArea']['name'] ?? '';
      $item['locality'] = $format_values['locality']['name'] ?? '';
      $item['dependent_locality'] = $format_values['dependentLocality']['name'] ?? '';
      if ($address = $this->prepareAddressDisplay($item)) {
        $elements[$delta] = [
          '#type' => 'container',
          '#children' => $address,
        ];
      }
    }
    return $elements;
  }

  /**
   * Prepare render array with address components.
   *
   * @param array $item
   *   Address values.
   *
   * @return array
   *   Render array.
   */
  private function prepareAddressDisplay(array $item) {
    $config = $this->getSetting('address_display');
    $countries = $this->countryRepository->getList();

    $elements = [];
    // Skip hidden or empty items.
    $config_exclude_hidden = [];
    foreach ($config as $key => $config_item) {
      if ($config_item['display'] && !empty($item[$key])) {
        $config_exclude_hidden[$key] = $config_item;
      }
    }

    // The key of the last displayed item.
    $last_key = $config_exclude_hidden ? array_keys($config_exclude_hidden)[count($config_exclude_hidden) - 1] : 0;

    foreach ($config_exclude_hidden as $key => $config_item) {
      if ($key == 'country_code') {
        $item[$key] = $countries[$item[$key]];
      }

      // Don't display the 'glue' separator for the last item.
      if ($key == $last_key) {
        $config_item['glue'] = '';
      }

      $elements[$key] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => [
          'class' => [
            'address-display-element',
            str_replace('_', '-', $key) . '-element',
          ],
        ],
        '#value' => $item[$key] . $config_item['glue'],
      ];
    }

    return $elements;
  }

}
