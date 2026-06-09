<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The JSON Field action.
 */
#[FieldWidgetAction(
  id: 'automator_json',
  label: new TranslatableMarkup('Automator JSON'),
  widget_types: ['json_textarea'],
  field_types: ['json', 'json_native', 'json_native_binary'],
)]
class Json extends AutomatorBaseAction {

  /**
   * {@inheritdoc}
   */
  public string $formElementProperty = 'value';

  /**
   * Ajax handler for Automators.
   */
  public function aiAutomatorsAjax(array &$form, FormStateInterface $form_state) {
    // Get the triggering element, as it contains the settings.
    $triggering_element = $form_state->getTriggeringElement();

    // Attempt to get context directly from the triggering element properties.
    $key = $triggering_element['#field_widget_action_field_delta'] ?? NULL;
    $form_key = $triggering_element['#field_widget_action_field_name'] ?? NULL;

    // Fallback logic if properties are missing.
    if ($form_key === NULL) {
      $array_parents = $triggering_element['#array_parents'];
      array_pop($array_parents);
      // Determine form key from parents (usually index 0).
      $form_key = $array_parents[0];

      if ($key === NULL) {
        // Try to guess delta from array parents (usually index 2).
        // Ensure we only pass an int or NULL.
        $potentialKey = $array_parents[2] ?? 0;
        $key = is_numeric($potentialKey) ? (int) $potentialKey : NULL;
      }
    }

    // Ensure key is strictly int or NULL.
    if ($key !== NULL) {
      $key = (int) $key;
    }

    $this->populateAutomatorValues($form, $form_state, $form_key, $key);

    return $form[$form_key];
  }

  /**
   * {@inheritdoc}
   */
  protected function saveFormValues(array &$form, string $form_key, $entity, ?int $key = NULL): array {
    $setValue = function ($index, $item) use (&$form, $form_key) {
      if (isset($form[$form_key]['widget'][$index])) {
        // Handle standard textarea structure where value is a child.
        if (isset($form[$form_key]['widget'][$index]['value'])) {
          $form[$form_key]['widget'][$index]['value']['#value'] = $item->value;
        }
        // Handle flattened widget structure (if applicable).
        elseif (isset($form[$form_key]['widget'][$index]['#type']) && $form[$form_key]['widget'][$index]['#type'] === 'textarea') {
          $form[$form_key]['widget'][$index]['#value'] = $item->value;
        }
      }
    };

    if (is_null($key)) {
      foreach ($entity->get($form_key) as $index => $item) {
        if ($item->value) {
          $setValue($index, $item);
        }
      }
    }
    else {
      if (isset($entity->get($form_key)[$key])) {
        $item = $entity->get($form_key)[$key];
        if ($item->value) {
          $setValue($key, $item);
        }
      }
    }

    return $form[$form_key];
  }

}
