<?php

namespace Drupal\select_or_other\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\WidgetBase as CoreWidgetBase;

/**
 * Base class for the 'select_or_other_*' widgets.
 *
 * Field types willing to enable one or several of the widgets defined in
 * select_or_other.module (select, radios/checkboxes, on/off checkbox) need to
 * implement the AllowedValuesInterface to specify the list of options to
 * display in the widgets.
 *
 * @see \Drupal\Core\TypedData\AllowedValuesInterface
 */
abstract class WidgetBase extends CoreWidgetBase {

  /**
   * Helper method to determine the identifying column for the field.
   *
   * @return string
   *   The name of the column.
   */
  protected function getColumn() {
    static $property_names;

    if (empty($property_names)) {
      $property_names = $this->fieldDefinition->getFieldStorageDefinition()
        ->getPropertyNames();
    }

    return reset($property_names);
  }

  /**
   * Helper method to determine if the field supports multiple values.
   *
   * @return bool
   *   Whether the field supports multiple values or not.
   */
  protected function isMultiple() {
    return $this->fieldDefinition->getFieldStorageDefinition()->isMultiple();
  }

  /**
   * Helper method to determine if the field is required.
   *
   * @return bool
   *   Whether the field is required or not.
   */
  protected function isRequired() {
    return $this->fieldDefinition->isRequired();
  }

  /**
   * {@inheritdoc}
   *
   * @codeCoverageIgnore
   *   Ignore this method because we would be testing if a hard coded array is
   *   equal to another hard coded array.
   */
  public static function defaultSettings() {
    return [
      'select_element_type' => 'select_or_other_select',
      'sort_options' => '',
      'other_placeholder' => '',
      'other_option' => '',
      'other_field_label' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['select_element_type'] = [
      '#title' => $this->t('Type of select form element'),
      '#type' => 'select',
      '#options' => $this->selectElementTypeOptions(),
      '#default_value' => $this->getSetting('select_element_type'),
    ];

    $form['sort_options'] = [
      '#title' => $this->t('Sort options by value'),
      '#type' => 'select',
      '#options' => $this->getAvailableSortOptions(),
      '#default_value' => $this->getSetting('sort_options'),
    ];

    $form['other_placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Other placeholder'),
      '#default_value' => $this->getSetting('other_placeholder'),
      '#description' => $this->t('Text that will be shown inside the field until a value is entered. This hint is usually a sample value or a brief description of the expected format.'),
    ];

    $form['other_option'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Other option'),
      '#description' => $this->t('Label of the option that the user will choose when they want to supply an other value.'),
      '#default_value' => $this->getSetting('other_option'),
    ];

    $form['other_field_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label of the Other field'),
      '#description' => $this->t('Label for the field in which the user will supply an other value.'),
      '#default_value' => $this->getSetting('other_field_label'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $options = $this->selectElementTypeOptions();
    $summary[] = $this->t('Type of select form element:') . $options[$this->getSetting('select_element_type')];

    $placeholder = $this->getSetting('other_placeholder');
    if (!empty($placeholder)) {
      $summary[] = $this->t('Placeholder: @placeholder', ['@placeholder' => $placeholder]);
    }
    else {
      $summary[] = $this->t('No placeholder');
    }

    if ($option = $this->getSetting('sort_options')) {
      $options = $this->getAvailableSortOptions();
      $summary[] = $options[$option];
    }

    if ($other_option = $this->getSetting('other_option')) {
      $summary[] = $this->t('Other option: @option', ['@option' => $other_option]);
    }

    if ($other_field_label = $this->getSetting('other_field_label')) {
      $summary[] = $this->t('Label of the Other field: @label', ['@label' => $other_field_label]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element += [
      '#no_empty_option' => $this->isDefaultValueWidget($form_state),
      '#type' => $this->getSetting('select_element_type'),
      '#default_value' => $this->getSelectedOptions($items),
      '#multiple' => $this->isMultiple(),
      '#key_column' => $this->getColumn(),
      '#other_placeholder' => $this->getSetting('other_placeholder'),
      '#other_option' => $this->getSetting('other_option'),
      '#other_field_label' => $this->getSetting('other_field_label'),
    ];

    $element['#options'] = $this->getOptions($items->getEntity());
    $element['#options'] = $this->sortOptions($element['#options']);

    // The rest of the $element is built by child method implementations.
    return $element;
  }

  /**
   * Adds the available options to the select or other element.
   *
   * @param array|null $options
   *   The options to sort.
   *
   * @return array
   *   The sorted options.
   */
  private function sortOptions($options) {
    if ($direction = $this->getSetting('sort_options')) {
      if ($direction === 'ASC') {
        uasort($options, 'strcasecmp');
      }
      elseif ($direction === 'DESC') {
        uasort($options, function ($a, $b) {
          return -1 * strcasecmp($a, $b);
        });
      }
    }
    return $options;
  }

  /**
   * Returns the array of options for the widget.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity this widget is used for.
   *
   * @return array
   *   The array of available options for the widget.
   */
  abstract protected function getOptions(FieldableEntityInterface $entity = NULL);

  /**
   * Determines selected options from the incoming field values.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field values.
   *
   * @return array
   *   The array of corresponding selected options.
   */
  protected function getSelectedOptions(FieldItemListInterface $items) {
    $selected_options = [];

    foreach ($items as $item) {
      $column = $this->getColumn();
      if ($value = $item->get($column)->getValue()) {
        $selected_options[] = $value;
      }
    }

    $selected_options = $this->prepareSelectedOptions($selected_options);

    if ($selected_options) {
      // We need to check against a flat list of options.
      $flattened_options = $this->flattenOptions($this->getOptions($items->getEntity()));

      foreach ($selected_options as $key => $selected_option) {
        // Remove the option if it does not exist in the options.
        if (!isset($flattened_options[$selected_option])) {
          unset($selected_options[$key]);
        }
      }
    }

    return $selected_options;
  }

  /**
   * Flattens an array of allowed values.
   *
   * @param array $array
   *   A single or multidimensional array.
   *
   * @return array
   *   The flattened array.
   */
  protected function flattenOptions(array $array) {
    $result = [];
    array_walk_recursive($array, function ($a, $b) use (&$result) {
      $result[$b] = $a;
    });
    return $result;
  }

  /**
   * Indicates whether the widgets support optgroups.
   *
   * @return bool
   *   TRUE if the widget supports optgroups, FALSE otherwise.
   *
   * @codeCoverageIgnore
   *   No need to test a hardcoded value.
   */
  protected function supportsGroups() {
    return FALSE;
  }

  /**
   * Prepares selected options for comparison to the available options.
   *
   * Sometimes widgets have to change the keys of their available options. This
   * method allows those widgets to do the same with the selected options to
   * ensure they actually end up selected in the widget.
   *
   * @param array $options
   *   The options to prepare.
   *
   * @return array
   *   The prepared option.
   */
  protected function prepareSelectedOptions(array $options) {
    return $options;
  }

  /**
   * Returns the types of select elements available for selection.
   *
   * @return array
   *   The available select element types.
   *
   * @codeCoverageIgnore
   *   Testing this method would only test if this hard-coded array equals the
   *   one in the test case.
   */
  private function selectElementTypeOptions() {
    return [
      'select_or_other_select' => $this->t('Select list'),
      'select_or_other_buttons' => $this->t('Check boxes/radio buttons'),
    ];
  }

  /**
   * Returns the available sorting options.
   *
   * @return array
   *   The available sorting options.
   *
   * @codeCoverageIgnore
   *   Testing this method would only test if this hard-coded array equals the
   *   one in the test case.
   */
  private function getAvailableSortOptions() {
    return [
      '' => $this->t('No sorting'),
      'ASC' => $this->t('Sorted ascending'),
      'DESC' => $this->t('Sorted descending'),
    ];
  }

}
