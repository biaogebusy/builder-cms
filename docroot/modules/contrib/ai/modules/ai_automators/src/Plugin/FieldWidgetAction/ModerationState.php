<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The Moderation State action.
 *
 * Uses the base-class AJAX + setFormInput contract. The
 * moderation_state_default widget nests its form control under a 'state'
 * sub-element, so transformFormInput remaps the field's 'value' into
 * ['state' => $value] for the per-delta user input.
 */
#[FieldWidgetAction(
  id: 'automator_moderation_state',
  label: new TranslatableMarkup('Automator Moderation State'),
  widget_types: ['moderation_state_default'],
  field_types: ['string'],
)]
class ModerationState extends AutomatorBaseAction {

  /**
   * {@inheritdoc}
   */
  protected function transformFormInput(ComplexDataInterface $item): array {
    $value = $item->get('value')?->getValue();
    return ['state' => (string) ($value ?? '')];
  }

}
