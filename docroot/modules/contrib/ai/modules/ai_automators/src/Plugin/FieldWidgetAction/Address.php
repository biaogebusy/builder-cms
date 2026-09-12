<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The Address Field Widget Action.
 */
#[FieldWidgetAction(
  id: 'automator_address',
  label: new TranslatableMarkup('Automator Address'),
  widget_types: ['address_default'],
  field_types: ['address'],
  category: new TranslatableMarkup('AI Automators'),
  multiple: FALSE,
)]
class Address extends AutomatorBaseAction {

  /**
   * {@inheritdoc}
   */
  protected bool $clearEntity = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function transformFormInput(ComplexDataInterface $item): array {
    return ['address' => parent::transformFormInput($item)];
  }

}
