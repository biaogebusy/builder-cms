<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The Moderation State action.
 */
#[FieldWidgetAction(
  id: 'automator_moderation_state',
  label: new TranslatableMarkup('Automator Moderation State'),
  widget_types: ['moderation_state_default'],
  field_types: ['string'],
)]
class ModerationState extends AutomatorBaseAction {

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
    array_pop($array_parents);
    $key = $array_parents[2] ?? 0;
    $form_key = $array_parents[0];

    return $this->populateAutomatorValues($form, $form_state, $form_key, $key);
  }

  /**
   * {@inheritdoc}
   */
  protected function saveFormValues(array &$form, string $form_key, $entity, ?int $key = NULL): array {
    if (is_null($key)) {
      // If no key is provided, we should iterate through all items.
      foreach ($entity->get($form_key) as $index => $item) {
        if ($item->get($this->formElementProperty)) {
          $form[$form_key]['widget'][$index]['state']['#default_value'] = $item->get($this->formElementProperty)->getValue();
        }
      }
    }
    else {
      // Handle specific key/index.
      if (isset($entity->get($form_key)[$key])) {
        $item = $entity->get($form_key)[$key];
        if ($item && $item->get($this->formElementProperty)) {
          $form[$form_key]['widget'][$key]['state']['#value'] = $item->get($this->formElementProperty)->getValue();
        }
      }
    }

    return $form[$form_key];
  }

}
