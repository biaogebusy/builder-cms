<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The Email action.
 *
 * Uses the base-class AJAX + setFormInput contract: $item->toArray() maps
 * cleanly to the per-delta ['value' => $email] shape that email_default
 * expects as user input.
 */
#[FieldWidgetAction(
  id: 'automator_email',
  label: new TranslatableMarkup('Automator Email'),
  widget_types: ['email_default'],
  field_types: ['email'],
)]
class Email extends AutomatorBaseAction {

}
