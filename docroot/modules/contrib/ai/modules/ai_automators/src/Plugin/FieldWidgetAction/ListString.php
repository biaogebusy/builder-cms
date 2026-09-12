<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The List String action.
 */
#[FieldWidgetAction(
  id: 'automator_list_string',
  label: new TranslatableMarkup('Automator List String'),
  widget_types: ['options_select', 'options_buttons'],
  field_types: ['list_string'],
  multiple: FALSE,
)]
class ListString extends ListBase {

}
