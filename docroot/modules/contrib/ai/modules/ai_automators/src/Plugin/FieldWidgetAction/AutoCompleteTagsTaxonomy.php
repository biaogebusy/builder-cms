<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The Tags Taxonomy Autocomplete action.
 *
 * Targets the entity_reference_autocomplete_tags widget — a single text
 * input where all referenced terms are stored as a comma-separated list,
 * not as per-delta sub-elements.
 */
#[FieldWidgetAction(
  id: 'automator_autocomplete_tags_on_taxonomy',
  label: new TranslatableMarkup('Automator Taxonomy'),
  widget_types: ['entity_reference_autocomplete_tags'],
  field_types: ['entity_reference'],
  category: new TranslatableMarkup('AI Automators'),
)]
class AutoCompleteTagsTaxonomy extends AutomatorBaseAction {

  /**
   * {@inheritdoc}
   *
   * The tags autocomplete widget renders a single text input where all
   * tags are stored as a comma-separated list. The default per-delta
   * setFormInput() would write the wrong shape.
   */
  protected function setFormInput(FieldableEntityInterface $entity, FormStateInterface $form_state, $form_key): void {
    $input = $form_state->getUserInput();
    $tags = [];
    foreach ($entity->get($form_key) as $item) {
      $term = $item->entity ?? NULL;
      if (!$term) {
        continue;
      }
      $tags[] = $term->label() . ' (' . $term->id() . ')';
    }
    $input[$form_key]['target_id'] = implode(', ', $tags);
    $form_state->setUserInput($input);
  }

}
