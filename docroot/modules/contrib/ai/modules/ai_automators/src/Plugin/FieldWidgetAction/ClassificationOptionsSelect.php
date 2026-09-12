<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * Field Widget Action for options_select, chosen_select and cshs widgets.
 */
#[FieldWidgetAction(
  id: 'classification_options_select',
  label: new TranslatableMarkup('Classification (Options/Chosen Select)'),
  widget_types: ['options_select', 'chosen_select', 'cshs'],
  field_types: ['entity_reference'],
  multiple: FALSE,
)]
class ClassificationOptionsSelect extends AutomatorBaseAction {

  /**
   * Target the 'target_id' of the taxonomy entity reference.
   */
  protected string $formElementProperty = 'target_id';

  /**
   * {@inheritdoc}
   *
   * The input shape depends on whether the widget declares
   * multiple_values=TRUE:
   * - TRUE (options_select, chosen_select): a single multi-value select;
   *   user input lives at $input[$form_key] as a flat list of ids.
   * - FALSE (cshs): one widget per delta with a `target_id` sub-element;
   *   user input lives at $input[$form_key][$delta]['target_id'].
   *
   * We branch on the widget plugin definition rather than hardcoding widget
   * IDs so any future widget in widget_types is handled based on its
   * declared shape.
   */
  protected function setFormInput(FieldableEntityInterface $entity, FormStateInterface $form_state, $form_key): void {
    $input = $form_state->getUserInput();

    if ($this->widgetHandlesMultipleValues($form_state, $form_key)) {
      $ids = [];
      foreach ($entity->get($form_key) as $item) {
        $ids[] = (string) $item->target_id;
      }
      $input[$form_key] = $ids;
    }
    else {
      foreach ($entity->get($form_key) as $delta => $item) {
        $input[$form_key][$delta] = [$this->formElementProperty => (string) $item->target_id];
      }
    }
    $form_state->setUserInput($input);
  }

}
