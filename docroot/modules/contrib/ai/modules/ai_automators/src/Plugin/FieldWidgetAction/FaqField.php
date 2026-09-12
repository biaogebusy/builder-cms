<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The FAQ Field action.
 */
#[FieldWidgetAction(
  id: 'automator_faqfield',
  label: new TranslatableMarkup('Automator FAQ Field'),
  widget_types: ['faqfield_default'],
  field_types: ['faqfield'],
  multiple: FALSE,
)]
class FaqField extends AutomatorBaseAction {

  /**
   * {@inheritdoc}
   */
  protected function transformFormInput(ComplexDataInterface $item): array {
    $values = parent::transformFormInput($item);
    // The FaqFieldItem setValue converts user input into its structure, this
    // method does reverse transformation.
    if (array_key_exists('answer_format', $values)) {
      $values['answer'] = [
        'value' => $values['answer'],
        'format' => $values['answer_format'],
      ];
      unset($values['answer_format']);
    }
    return $values;
  }

}
