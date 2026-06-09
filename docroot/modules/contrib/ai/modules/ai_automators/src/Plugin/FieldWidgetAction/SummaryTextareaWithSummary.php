<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * Provides a Field Widget Action for summary generation.
 */
#[FieldWidgetAction(
  id: 'summary_textarea_with_summary',
  label: new TranslatableMarkup('Generate Summary (Textarea with Summary)'),
  widget_types: ['text_textarea_with_summary'],
  field_types: ['text_with_summary'],
)]
class SummaryTextareaWithSummary extends AutomatorBaseAction {

  /**
   * Do not clear the entity values.
   */
  protected bool $clearEntity = FALSE;

  /**
   * Target the 'summary' subfield for auto-filling.
   */
  public string $formElementProperty = 'summary';

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
