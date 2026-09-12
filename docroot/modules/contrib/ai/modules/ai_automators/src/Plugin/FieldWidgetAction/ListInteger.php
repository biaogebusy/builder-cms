<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The List Integer action.
 */
#[FieldWidgetAction(
  id: 'automator_list_integer',
  label: new TranslatableMarkup('Automator List Integer'),
  widget_types: ['options_select', 'options_buttons'],
  field_types: ['list_integer'],
  multiple: FALSE,
)]
class ListInteger extends ListBase {

}
