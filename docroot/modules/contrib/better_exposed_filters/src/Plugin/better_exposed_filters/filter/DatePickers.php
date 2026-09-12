<?php

namespace Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter;

use Drupal\better_exposed_filters\Attribute\FiltersWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Date picker widget implementation.
 */
#[FiltersWidget(
  id: 'bef_datepicker',
  title: new TranslatableMarkup('Date Picker'),
)]
class DatePickers extends FilterWidgetBase {

  use LoggerChannelTrait;

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(mixed $filter = NULL, array $filter_options = []): bool {
    /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
    $is_applicable = FALSE;

    if ((is_a($filter, 'Drupal\views\Plugin\views\filter\Date') || !empty($filter->date_handler)) && !$filter->isAGroup()) {
      $is_applicable = TRUE;
    }

    return $is_applicable;
  }

  /**
   * In case of date offsets as a default value, convert to dates.
   *
   * @param array $element
   *   The form element to process.
   * @param bool $is_double_date
   *   Indicates this is a double input date.
   * @param string $field_id
   *   The element field id.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function convertOffsets(array &$element, bool $is_double_date, string $field_id, FormStateInterface $form_state): void {
    $options = $this->handler->options;

    if ($options['value']['type'] !== 'offset') {
      return;
    }

    $userInput = $form_state->getUserInput();

    // Check if a Y-m-d date was submitted (not an offset string).
    // If so, we need to change the filter type from 'offset' to 'date'
    // so Views processes the value correctly.
    //
    // The shape of $userInput[$field_id] must match what the widget expects
    // (array for double-date, string for single). URL-encoded bracket input
    // (e.g. ?field%5Bmin%5D=…) on a single-date filter can flip that shape
    // and blow up preg_match(); guard both branches.
    $hasDateInput = FALSE;
    if ($is_double_date && isset($userInput[$field_id]) && is_array($userInput[$field_id])) {
      $min = $userInput[$field_id]['min'] ?? NULL;
      $max = $userInput[$field_id]['max'] ?? NULL;
      if ((is_string($min) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $min))
        || (is_string($max) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $max))) {
        $hasDateInput = TRUE;
      }
    }
    elseif (!$is_double_date && isset($userInput[$field_id]) && is_string($userInput[$field_id])) {
      if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $userInput[$field_id])) {
        $hasDateInput = TRUE;
      }
    }

    // Change the filter type to 'date' when a Y-m-d date is submitted.
    // This ensures Views processes the value as an absolute date, not as an
    // offset from current time.
    if ($hasDateInput) {
      $this->handler->options['value']['type'] = 'date';
      $this->handler->value['type'] = 'date';
    }

    try {
      if ($is_double_date) {
        $inputBundle = is_array($userInput[$field_id] ?? NULL) ? $userInput[$field_id] : [];
        foreach (['min', 'max'] as $key) {
          // Convert offset initial values to dates for display.
          $raw = $inputBundle[$key] ?? NULL;
          if (is_string($raw) && $raw !== '') {
            $date = new \DateTime($raw);
          }
          else {
            $date = new \DateTime($element[$key]['#default_value']);
          }

          // Set the default_value attribute for JavaScript to use for display.
          $element[$key]['#attributes']['default_value'] = $date->format('Y-m-d');
        }
      }
      else {
        // Convert offset initial values to dates for display.
        $raw = $userInput[$field_id] ?? NULL;
        if (is_string($raw) && $raw !== '') {
          $date = new \DateTime($raw);
        }
        else {
          $date = new \DateTime($element['#default_value']);
        }

        // Set the default_value attribute for JavaScript to use for display.
        $element['#attributes']['default_value'] = $date->format('Y-m-d');
      }
    }
    catch (\Exception $e) {
      $this->getLogger('better_exposed_filters')->log(RfcLogLevel::ERROR, $e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function exposedFormAlter(array &$form, FormStateInterface $form_state): void {
    $field_id = $this->getExposedFilterFieldId();

    // Handle wrapper element added to exposed filters
    // in https://www.drupal.org/project/drupal/issues/2625136.
    $wrapper_id = $field_id . '_wrapper';
    if (!isset($form[$field_id]) && isset($form[$wrapper_id])) {
      $element = &$form[$wrapper_id][$field_id];
    }
    else {
      $element = &$form[$field_id];
    }

    parent::exposedFormAlter($form, $form_state);

    // Single Date API-based input element (operator not exposed).
    $is_single_date = isset($element['#type']);

    // Double Date-API-based input elements such as "in-between".
    $is_double_date = isset($element['min']['#type']) && isset($element['max']['#type']);

    // When the operator is exposed, all sub-elements (value, min, max) exist
    // simultaneously with #states controlling visibility. Handle the single
    // value sub-element in addition to min/max.
    $has_value_sub_element = isset($element['value']['#type']);

    if ($is_single_date) {
      $element['#type'] = 'date';
      $element['#attributes']['class'][] = 'bef-datepicker';
      $element['#attributes']['autocomplete'] = 'off';
    }
    elseif ($is_double_date || $has_value_sub_element) {
      if ($has_value_sub_element) {
        $element['value']['#type'] = 'date';
        $element['value']['#attributes']['class'][] = 'bef-datepicker';
        $element['value']['#attributes']['autocomplete'] = 'off';
        if (empty($element['value']['#title'])) {
          $element['value']['#title'] = 'Date';
          $element['value']['#title_display'] = 'invisible';
        }
      }
      if ($is_double_date) {
        $element['min']['#type'] = 'date';
        $element['max']['#type'] = 'date';
        $element['min']['#attributes']['class'][] = 'bef-datepicker';
        $element['max']['#attributes']['class'][] = 'bef-datepicker';
        $element['min']['#attributes']['autocomplete'] = 'off';
        $element['max']['#attributes']['autocomplete'] = 'off';
      }
    }

    $this->convertOffsets($element, $is_double_date, $field_id, $form_state);
    $form['#attached']['library'][] = 'better_exposed_filters/datepickers';
  }

}
