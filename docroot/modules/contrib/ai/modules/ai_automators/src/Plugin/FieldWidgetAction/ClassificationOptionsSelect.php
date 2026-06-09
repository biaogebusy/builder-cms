<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * Provides a Field Widget Action for options_select and chosen_select widgets.
 */
#[FieldWidgetAction(
  id: 'classification_options_select',
  label: new TranslatableMarkup('Classification (Options/Chosen Select)'),
  widget_types: ['options_select', 'chosen_select', 'cshs'],
  field_types: ['entity_reference'],
  multiple: FALSE,
)]
class ClassificationOptionsSelect extends AutomatorBaseAction {

  /**
   * Target the 'target_id' of the taxonomy entity reference.
   */
  public string $formElementProperty = 'target_id';

  /**
   * Ajax handler for AI Automator.
   */
  public function aiAutomatorsAjax(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $array_parents = $triggering_element['#array_parents'];
    array_pop($array_parents);
    $array_parents[] = static::FORM_ELEMENT_PROPERTY;
    $form_key = $array_parents[0];

    return $this->populateAutomatorValues($form, $form_state, $form_key, NULL);
  }

}
