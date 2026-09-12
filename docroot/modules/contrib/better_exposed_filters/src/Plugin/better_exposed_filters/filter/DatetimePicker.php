<?php

namespace Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter;

use Drupal\better_exposed_filters\Attribute\FiltersWidget;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Date & time picker widget - HTML5 datetime-local inputs for date filters.
 */
#[FiltersWidget(
  id: 'bef_datetimepicker',
  title: new TranslatableMarkup('Date & Time Picker'),
)]
class DatetimePicker extends FilterWidgetBase {

  use LoggerChannelTrait;

  /**
   * Value format expected by datetime-local HTML inputs.
   */
  const DATETIME_LOCAL_FORMAT = 'Y-m-d\TH:i';

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(mixed $filter = NULL, array $filter_options = []): bool {
    /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
    return (is_a($filter, 'Drupal\views\Plugin\views\filter\Date') || !empty($filter->date_handler))
      && !$filter->isAGroup();
  }

  /**
   * Converts a date expression (offset or absolute) to datetime-local format.
   *
   * Both strtotime-compatible offsets (+1 day) and absolute date strings are
   * accepted. DrupalDateTime is used so that "now" is expressed in the site
   * timezone (Drupal sets PHP's default timezone to UTC, so a bare \DateTime
   * would otherwise compute offsets and format in UTC regardless of the
   * site's timezone configuration).
   */
  protected function toInputFormat(string $value): ?string {
    if ($value === '') {
      return NULL;
    }
    $site_timezone = $this->configFactory->get('system.date')->get('timezone.default') ?: 'UTC';
    $date = new DrupalDateTime($value, new \DateTimeZone($site_timezone));
    if ($date->hasErrors()) {
      foreach ($date->getErrors() as $error) {
        $this->getLogger('better_exposed_filters')->log(RfcLogLevel::ERROR, $error);
      }
      return NULL;
    }
    return $date->format(self::DATETIME_LOCAL_FORMAT);
  }

  /**
   * Converts the filter's configured default values to datetime-local format.
   *
   * Handles two element structures produced by NumericFilter:
   *  - Nested: $element['value'] / $element['min'] / $element['max'] are the
   *    actual inputs (operator exposed, $which = 'all' / 'minmax').
   *  - Flat: $element itself is the single-value input (operator locked,
   *    $which = 'value').
   *
   * In both cases the form-state user input (set by NumericFilter::valueForm()
   * to the raw offset/date string before our alter runs) is also updated so
   * that the subsequent doBuildForm() pass assigns the formatted value to
   * #value rather than the uninterpretable raw string.
   *
   * @param array $element
   *   The filter's form element (by reference).
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The exposed form state.
   * @param string $field_id
   *   The exposed-filter identifier (key in $form and in user_input).
   */
  protected function convertDefaults(array &$element, FormStateInterface $form_state, string $field_id): void {
    /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $handler */
    $handler = $this->handler;
    $options = $handler->options;

    if (empty($options['value']['type'])) {
      return;
    }

    $configured = $options['value'];
    unset($configured['type']);

    $user_input = $form_state->getUserInput();
    $changed = FALSE;

    // --- Nested sub-elements (standard exposed operator or between) ---
    foreach (['value', 'min', 'max'] as $key) {
      if (!array_key_exists('#default_value', $element[$key] ?? [])) {
        continue;
      }
      $raw = $configured[$key] ?? '';
      if ($raw === '' || $element[$key]['#default_value'] !== $raw) {
        continue;
      }
      $formatted = $this->toInputFormat($raw);
      if ($formatted === NULL) {
        continue;
      }
      $element[$key]['#default_value'] = $formatted;

      // Update user_input so doBuildForm() sets #value to the formatted value
      // instead of the raw offset string, which is invalid for datetime-local.
      if (isset($user_input[$field_id][$key]) && $user_input[$field_id][$key] === $raw) {
        $user_input[$field_id][$key] = $formatted;
        $changed = TRUE;
      }
    }

    // --- Flat element (locked single-value operator, $which = 'value') ---
    // In this case $element itself is the input; no 'value'/'min'/'max'
    // sub-keys, and user_input[$field_id] is a plain string, not an array.
    $has_sub_inputs = isset($element['value']) || isset($element['min']) || isset($element['max']);
    if (!$has_sub_inputs && array_key_exists('#default_value', $element)) {
      $raw = $configured['value'] ?? '';
      if ($raw !== '' && $element['#default_value'] === $raw) {
        $formatted = $this->toInputFormat($raw);
        if ($formatted !== NULL) {
          $element['#default_value'] = $formatted;

          if (isset($user_input[$field_id])
              && !is_array($user_input[$field_id])
              && $user_input[$field_id] === $raw) {
            $user_input[$field_id] = $formatted;
            $changed = TRUE;
          }
        }
      }
    }

    if ($changed) {
      $form_state->setUserInput($user_input);
    }
  }

  /**
   * Converts a form sub-element to an HTML5 datetime-local input.
   *
   * @param array $sub_element
   *   The form sub-element to convert (by reference).
   */
  protected function convertToDatetimeLocal(array &$sub_element): void {
    // Use 'date' as the Drupal element base so its value callback and
    // preprocess chain run; override the HTML type attribute to datetime-local.
    $sub_element['#type'] = 'date';
    $sub_element['#attributes']['type'] = 'datetime-local';
    $sub_element['#attributes']['class'][] = 'bef-datetimepicker';
    $sub_element['#attributes']['autocomplete'] = 'off';
  }

  /**
   * {@inheritdoc}
   */
  public function exposedFormAlter(array &$form, FormStateInterface $form_state): void {
    $field_id = $this->getExposedFilterFieldId();
    $wrapper_id = $field_id . '_wrapper';

    // Detect whether the element is wrapped (between/range) BEFORE calling
    // parent, because parent may restructure the wrapper into a double-wrap.
    // For flat (locked single-value operator): element lives at
    // $form[$field_id] and parent does NOT restructure it, so the reference
    // set here stays valid. For wrapped (between): parent replaces
    // $form[$wrapper_id] with a container, making any reference into it
    // stale; we re-establish after parent.
    $is_wrapped = isset($form[$wrapper_id][$field_id]);
    if (!$is_wrapped) {
      // Flat case: mirrors the Number.php pattern (reference before parent).
      $element = &$form[$field_id];
    }

    parent::exposedFormAlter($form, $form_state);

    if ($is_wrapped) {
      // After parent's double-wrap: [$wrapper_id][$wrapper_id][$field_id].
      if (isset($form[$wrapper_id][$wrapper_id][$field_id])) {
        $element = &$form[$wrapper_id][$wrapper_id][$field_id];
      }
      else {
        $element = &$form[$wrapper_id][$field_id];
      }
    }

    // Date-API single input (date_text comes from the contrib Date API module).
    $is_single_date_api = isset($element['value']['#type'])
      && $element['value']['#type'] === 'date_text';

    // Date-API between (min/max) inputs.
    $is_double_date_api = isset($element['min']['#type'], $element['max']['#type'])
      && $element['min']['#type'] === 'date_text'
      && $element['max']['#type'] === 'date_text';

    if ($is_single_date_api) {
      $this->convertToDatetimeLocal($element['value']);
    }
    elseif ($is_double_date_api) {
      $this->convertToDatetimeLocal($element['min']);
      $this->convertToDatetimeLocal($element['max']);
    }
    else {
      // Standard Views date filter (NumericFilter-based textfield elements).
      // 'value' covers the exposed-operator case ($which = 'all' in
      // NumericFilter::valueForm), where the element is a container with
      // 'value', 'min', and 'max' sub-inputs. 'min'/'max' cover the between
      // case ($which = 'minmax').
      $sub_fields = ['value', 'min', 'max'];
      $has_sub_inputs = (bool) array_intersect($sub_fields, array_keys($element));
      if ($has_sub_inputs) {
        foreach ($sub_fields as $key) {
          if (array_key_exists($key, $element)) {
            $this->convertToDatetimeLocal($element[$key]);
          }
        }
      }
      else {
        // Flat element — the element itself is the single-value input.
        $element['#type'] = 'date';
        $element['#attributes']['type'] = 'datetime-local';
        $element['#attributes']['class'][] = 'bef-datetimepicker';
        $element['#attributes']['autocomplete'] = 'off';
      }
    }

    // Convert Views-configured defaults (offset or fixed) to datetime-local
    // format so the input is pre-filled correctly on first load.
    $this->convertDefaults($element, $form_state, $field_id);
  }

}
