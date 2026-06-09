<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The List Float action.
 */
#[FieldWidgetAction(
  id: 'automator_list_float',
  label: new TranslatableMarkup('Automator List Float'),
  widget_types: ['options_select', 'options_buttons'],
  field_types: ['list_float'],
  multiple: FALSE,
)]
class ListFloat extends AutomatorBaseAction {

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
    $array_parents = $triggering_element['#array_parents'];
    array_pop($array_parents);
    $array_parents[] = $this->formElementProperty;
    $key = $array_parents[2] ?? 0;

    // Cast to integer to ensure proper type.
    $key = is_numeric($key) ? (int) $key : 0;

    $form_key = $array_parents[0];

    return $this->populateAutomatorValues($form, $form_state, $form_key, $key);
  }

  /**
   * {@inheritdoc}
   */
  protected function saveFormValues(array &$form, string $form_key, $entity, ?int $key = NULL): array {
    // For list fields, get the first (and only) item.
    if (isset($entity->get($form_key)->getValue()[0]) && $entity->get($form_key)->getValue()[0]->get($this->formElementProperty) != NULL) {
      $value = $entity->get($form_key)->getValue()[0]->get($this->formElementProperty)->getValue();

      // For both dropdown and radio buttons, set the default value.
      $form[$form_key]['widget']['#default_value'] = $value;

      // Check if this is an options_select vs options_buttons widget.
      if (isset($form[$form_key]['widget']['#type']) && $form[$form_key]['widget']['#type'] === 'select') {
        // For dropdown widget.
        $form[$form_key]['widget']['#value'] = $value;
      }
      else {
        // For radio buttons widget.
        if (isset($form[$form_key]['widget'][$value])) {
          $form[$form_key]['widget'][$value]['#attributes']['checked'] = 'checked';
        }
      }
    }

    return $form[$form_key];
  }

}
