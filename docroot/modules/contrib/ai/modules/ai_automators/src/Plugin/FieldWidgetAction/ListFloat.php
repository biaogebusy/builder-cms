<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The List Float action.
 */
#[FieldWidgetAction(
  id: 'automator_list_float',
  label: new TranslatableMarkup('Automator List Float'),
  widget_types: ['options_select', 'options_buttons'],
  field_types: ['list_float'],
  multiple: FALSE,
)]
class ListFloat extends ListBase {

}
