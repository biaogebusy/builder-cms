<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The Office Hours action.
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
   */
  public string $formElementProperty = 'value';

  /**
   * Do not filter empty items, as day 0 (Sunday) is valid but falsy.
   *
   * @var bool
   */
  protected bool $clearEntity = FALSE;

  /**
   * Ajax handler for Automators.
   */
  public function aiAutomatorsAjax(array &$form, FormStateInterface $form_state) {
    // Get the triggering element, as it contains the settings.
    $triggering_element = $form_state->getTriggeringElement();
    $array_parents = $triggering_element['#array_parents'];
    array_pop($array_parents);
    $array_parents[] = $this->formElementProperty;
    $raw_key = $array_parents[2] ?? NULL;
    $key = is_numeric($raw_key) ? (int) $raw_key : NULL;
    $form_key = $array_parents[0];
    return $this->populateAutomatorValues($form, $form_state, $form_key, $key);
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
