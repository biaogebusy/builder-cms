<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * Field Widget Action for LLM Number on number field widget.
 */
#[FieldWidgetAction(
  id: 'llm_number_number_default',
  label: new TranslatableMarkup('LLM Number Generator'),
  widget_types: ['number'],
  field_types: ['integer', 'float'],
  category: new TranslatableMarkup('AI Automators'),
)]
class LlmNumberNumberDefault extends AutomatorBaseAction {

  /**
   * The form element property.
   */
  public string $formElementProperty = 'value';

  /**
   * Ajax handler for AI Automator.
   */
  public function aiAutomatorsAjax(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $array_parents = $triggering_element['#array_parents'];
    array_pop($array_parents);
    $array_parents[] = static::FORM_ELEMENT_PROPERTY;
    $form_key = $array_parents[0];
    $key = $array_parents[2] ?? 0;

    return $this->populateAutomatorValues($form, $form_state, $form_key, $key);
  }

}
