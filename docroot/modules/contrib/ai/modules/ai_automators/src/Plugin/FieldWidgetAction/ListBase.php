<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Shared dispatch for single-value list_* widget actions.
 *
 * The options_select / options_buttons widgets render the list as a single
 * form control per field, so user input is a scalar at $input[$form_key]
 * — not the per-delta array the default setFormInput would write. Targets
 * list_integer, list_string and list_float through the concrete subclasses
 * which only carry the plugin-attribute metadata.
 */
abstract class ListBase extends AutomatorBaseAction {

  /**
   * {@inheritdoc}
   */
  protected function setFormInput(FieldableEntityInterface $entity, FormStateInterface $form_state, $form_key): void {
    $first = $entity->get($form_key)->first();
    if (!$first) {
      return;
    }
    $input = $form_state->getUserInput();
    $input[$form_key] = (string) $first->value;
    $form_state->setUserInput($input);
  }

}
