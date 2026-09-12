<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The JSON Field action.
 *
 * Uses the base-class AJAX + setFormInput contract: the json_textarea
 * widget expects per-delta ['value' => $json] user input, which is what
 * $item->toArray() produces.
 */
#[FieldWidgetAction(
  id: 'automator_json',
  label: new TranslatableMarkup('Automator JSON'),
  widget_types: ['json_textarea'],
  field_types: ['json', 'json_native', 'json_native_binary'],
)]
class Json extends AutomatorBaseAction {

}
