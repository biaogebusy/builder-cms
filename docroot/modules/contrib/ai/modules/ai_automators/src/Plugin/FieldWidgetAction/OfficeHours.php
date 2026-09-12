<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The Office Hours action.
 *
 * Uses the legacy dispatch (custom aiAutomatorsAjax + saveFormValues) —
 * the office_hours widget pre-allocates slot elements per day and maps
 * each entity item to a day-specific slot, which doesn't fit the generic
 * per-delta setFormInput user-input contract.
 */
#[FieldWidgetAction(
  id: 'automator_office_hours',
  label: new TranslatableMarkup('Automator Office Hours'),
  widget_types: ['office_hours_default', 'office_hours_list'],
  field_types: ['office_hours'],
)]
class OfficeHours extends AutomatorBaseAction {

  /**
   * {@inheritdoc}
   *
   * Do not filter empty items: day 0 (Sunday) is a valid value but falsy
   * under the default filter.
   */
  protected bool $clearEntity = FALSE;

  /**
   * {@inheritdoc}
   *
   * Opt out of submit-phase automator run — saveFormValues must operate
   * on the REBUILT form's render tree so the day-slot mapping is
   * carried into the AJAX response.
   */
  public function runAutomatorSubmit(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * {@inheritdoc}
   *
   * No-op: OfficeHours delivers values via saveFormValues (form render
   * tree), not $form_state->getUserInput().
   */
  protected function setFormInput(FieldableEntityInterface $entity, FormStateInterface $form_state, $form_key): void {
  }

  /**
   * {@inheritdoc}
   *
   * Runs the automator on the rebuilt form so saveFormValues can map
   * day entries into their pre-allocated slots on the widget render array.
   */
  public function aiAutomatorsAjax(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $form_key = $triggering_element['#field_widget_action_field_name'] ?? NULL;
    if ($form_key === NULL) {
      $array_parents = $triggering_element['#array_parents'];
      array_pop($array_parents);
      $form_key = $array_parents[0] ?? NULL;
    }
    if (!$form_key || !isset($form[$form_key])) {
      return [];
    }
    $key = $triggering_element['#field_widget_action_field_delta'] ?? NULL;
    return $this->populateAutomatorValues($form, $form_state, $form_key, is_numeric($key) ? (int) $key : NULL);
  }

  /**
   * {@inheritdoc}
   */
  protected function saveFormValues(array &$form, string $form_key, $entity, ?int $key = NULL): array {
    // Build mapping from day number to form slot indices.
    $day_slots = [];
    foreach ($form[$form_key]['widget']['value'] as $slot_index => $slot) {
      if (!is_int($slot_index)) {
        continue;
      }
      $day = $slot['#value']['day'] ?? NULL;
      if ($day !== NULL) {
        $day_slots[$day][] = $slot_index;
      }
    }

    // Track which delta we're on for each day.
    $day_used = [];

    foreach ($entity->get($form_key) as $item) {
      $day = $item->get('day')->getValue();
      $delta = $day_used[$day] ?? 0;
      $day_used[$day] = $delta + 1;

      if (!isset($day_slots[$day][$delta])) {
        continue;
      }

      $slot_index = $day_slots[$day][$delta];
      $slot = &$form[$form_key]['widget']['value'][$slot_index];

      foreach (['starthours', 'endhours'] as $property) {
        $value = $item->get($property)->getValue();
        if ($value === NULL) {
          continue;
        }

        // Pad to 4 digits for internal format (e.g. 500 -> "0500").
        $padded = str_pad($value, 4, '0', STR_PAD_LEFT);
        // Format as HH:MM for the HTML time input.
        $time_string = substr($padded, 0, 2) . ':' . substr($padded, 2, 2);

        // Set on the parent element's composite value.
        $slot['#value'][$property] = $padded;
        $slot['#default_value'][$property] = $padded;

        // Set on the rendered time input sub-element.
        if (isset($slot[$property]['time'])) {
          $slot[$property]['time']['#value'] = $time_string;
          $slot[$property]['time']['#default_value'] = $time_string;
        }
      }

      // Set comment if the field has it and the entity item has a value.
      $comment = $item->get('comment')->getValue();
      if ($comment !== NULL && $comment !== '' && isset($slot['comment'])) {
        $slot['#value']['comment'] = $comment;
        $slot['#default_value']['comment'] = $comment;
        $slot['comment']['#value'] = $comment;
        $slot['comment']['#default_value'] = $comment;
      }
    }

    return $form[$form_key];
  }

}
