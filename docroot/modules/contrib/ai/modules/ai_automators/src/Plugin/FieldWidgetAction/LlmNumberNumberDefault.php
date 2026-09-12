<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * Field Widget Action for LLM Number on number field widget.
 *
 * Uses the base-class AJAX + setFormInput contract: $item->toArray() maps
 * cleanly to the per-delta ['value' => $number] shape the 'number' widget
 * expects as user input.
 */
#[FieldWidgetAction(
  id: 'llm_number_number_default',
  label: new TranslatableMarkup('LLM Number Generator'),
  widget_types: ['number'],
  field_types: ['integer', 'float'],
  category: new TranslatableMarkup('AI Automators'),
)]
class LlmNumberNumberDefault extends AutomatorBaseAction {

}
