<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The Text action.
 */
#[FieldWidgetAction(
  id: 'automator_text',
  label: new TranslatableMarkup('Automator Text Suggestion'),
  widget_types: [
    'string_textfield',
    'string_textarea',
    'text_textfield',
    'text_textarea',
    'text_textarea_with_summary',
  ],
  field_types: [
    'string',
    'string_long',
    'text',
    'text_long',
    'text_with_summary',
  ],
  category: new TranslatableMarkup('AI Automators'),
)]
class Text extends AutomatorBaseAction {

  /**
   * Ajax handler for Automators.
   */
  public function aiAutomatorsAjax(array &$form, FormStateInterface $form_state) {
    // Get the triggering element, as it contains the settings.
    $triggering_element = $form_state->getTriggeringElement();
    $array_parents = $triggering_element['#array_parents'];
    // @todo Best practice.
    $form_key = $array_parents[0];
    $key = $array_parents[2] ?? 0;
    return $this->populateAutomatorValues($form, $form_state, $form_key, $key);
  }

}
