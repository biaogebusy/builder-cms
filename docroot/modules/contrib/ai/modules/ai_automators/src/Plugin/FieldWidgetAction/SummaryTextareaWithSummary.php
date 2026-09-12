<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * Provides a Field Widget Action for summary generation.
 *
 * Only the 'summary' sub-element is written. The main 'value' and
 * 'format' sub-elements are left untouched so that any in-flight edits
 * the user made to the main text area are preserved when they click the
 * Generate Summary button.
 */
#[FieldWidgetAction(
  id: 'summary_textarea_with_summary',
  label: new TranslatableMarkup('Generate Summary (Textarea with Summary)'),
  widget_types: ['text_textarea_with_summary'],
  field_types: ['text_with_summary'],
)]
class SummaryTextareaWithSummary extends AutomatorBaseAction {

  /**
   * {@inheritdoc}
   */
  protected bool $clearEntity = FALSE;

  /**
   * {@inheritdoc}
   *
   * Merges only the 'summary' sub-field into existing per-delta user
   * input so browser-submitted 'value' / 'format' entries aren't
   * clobbered.
   */
  protected function setFormInput(FieldableEntityInterface $entity, FormStateInterface $form_state, $form_key): void {
    $input = $form_state->getUserInput();
    foreach ($entity->get($form_key) as $index => $item) {
      if (!isset($input[$form_key][$index]) || !is_array($input[$form_key][$index])) {
        $input[$form_key][$index] = [];
      }
      $input[$form_key][$index]['summary'] = (string) ($item->summary ?? '');
    }
    $form_state->setUserInput($input);
  }

}
