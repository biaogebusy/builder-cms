<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The Text action.
 *
 * Uses the base-class AJAX + setFormInput contract: $item->toArray() maps
 * cleanly to the per-delta ['value' => $text] shape that
 * string_textfield / string_textarea / text_textfield / text_textarea
 * widgets expect as user input.
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

}
