<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The AltText action.
 */
#[FieldWidgetAction(
  id: 'automator_autocomplete_tags_on_taxonomy',
  label: new TranslatableMarkup('Automator Taxonomy'),
  widget_types: ['entity_reference_autocomplete_tags'],
  field_types: ['entity_reference'],
  category: new TranslatableMarkup('AI Automators'),
)]
class AutoCompleteTagsTaxonomy extends AutomatorBaseAction {

  /**
   * {@inheritdoc}
   */
  public string $formElementProperty = 'target_id';

  /**
   * Ajax handler for Automators.
   */
  public function aiAutomatorsAjax(array &$form, FormStateInterface $form_state) {
    // Get the triggering element, as it contains the settings.
    $triggering_element = $form_state->getTriggeringElement();
    $array_parents = $triggering_element['#array_parents'];
    array_pop($array_parents);
    $array_parents[] = static::FORM_ELEMENT_PROPERTY;
    $form_key = $array_parents[0];
    return $this->populateAutomatorValues($form, $form_state, $form_key, NULL);
  }

  /**
   * {@inheritdoc}
   */
  protected function saveFormValues(array &$form, string $form_key, $entity, ?int $key = NULL): array {
    // Specific saving for autocomplete tags on taxonomy.
    if (is_null($key)) {
      // If not key is provided, we should iterate through all items.
      $text_items = [];
      foreach ($entity->get($form_key) as $index => $item) {
        if ($item->get($this->formElementProperty)) {
          $form[$form_key]['widget']['target_id']['#default_value'][$index] = $item->entity;
          $text_items[] = $item->entity->label() . ' (' . $item->entity->id() . ')';
        }
      }
      $form[$form_key]['widget']['target_id']['#value'] = implode(', ', $text_items);
    }
    else {
      if (isset($entity->get($form_key)[0])) {
        $item = $entity->get($form_key)[0];
        $text_items = [];
        if ($item->get($this->formElementProperty)) {
          $form[$form_key]['widget']['target_id']['#default_value'][$key] = $item->entity;
          $text_items[] = $item->entity->label() . ' (' . $item->entity->id() . ')';
        }
        $form[$form_key]['widget']['target_id']['#value'] = implode(', ', $text_items);
      }
    }

    return $form[$form_key];
  }

}
