<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The Boolean action.
 */
#[FieldWidgetAction(
  id: 'automator_boolean',
  label: new TranslatableMarkup('Automator Boolean'),
  widget_types: ['boolean_checkbox'],
  field_types: ['boolean'],
)]
class Boolean extends AutomatorBaseAction {

  /**
   * {@inheritdoc}
   *
   * The boolean_checkbox widget declares multiple_values=TRUE, so the user
   * input is flat at $input[$form_key]['value'] (not per-delta). Writing
   * per-delta would be ignored on form rebuild and the checkbox state would
   * not update.
   */
  protected function setFormInput(FieldableEntityInterface $entity, FormStateInterface $form_state, $form_key): void {
    $input = $form_state->getUserInput();
    $first = $entity->get($form_key)->first();
    $input[$form_key] = ['value' => ($first && $first->value) ? '1' : '0'];
    $form_state->setUserInput($input);
  }

}
