<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The Telephone action.
 *
 * Uses the base-class AJAX + setFormInput contract: $item->toArray() maps
 * cleanly to the per-delta ['value' => $number] shape telephone_default
 * expects as user input.
 */
#[FieldWidgetAction(
  id: 'automator_telephone',
  label: new TranslatableMarkup('Automator Telephone'),
  widget_types: ['telephone_default'],
  field_types: ['telephone'],
  multiple: FALSE,
)]
class Telephone extends AutomatorBaseAction {

}
