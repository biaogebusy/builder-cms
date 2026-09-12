<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * Field Widget Action for LLM Link on link_default widget.
 *
 * Uses the base-class AJAX + setFormInput contract. The link_default
 * widget renders uri + title as sibling sub-elements; transformFormInput
 * drops the LinkItem 'options' property (stored but not a form control).
 */
#[FieldWidgetAction(
  id: 'llm_link_link_default',
  label: new TranslatableMarkup('LLM Link Generator'),
  widget_types: ['link_default'],
  field_types: ['link'],
  category: new TranslatableMarkup('AI Automators'),
)]
class LlmLinkLinkDefault extends AutomatorBaseAction {

  /**
   * {@inheritdoc}
   */
  protected function transformFormInput(ComplexDataInterface $item): array {
    $values = parent::transformFormInput($item);
    // 'options' is persisted on LinkItem but has no matching form control.
    unset($values['options']);
    return $values;
  }

}
