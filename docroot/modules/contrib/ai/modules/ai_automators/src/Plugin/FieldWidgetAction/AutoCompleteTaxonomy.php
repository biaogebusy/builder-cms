<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The Autocomplete widget for entity reference fields with autocomplete widget.
 *
 * This handles entity reference fields where each value has its
 * own form element (unlike tags which uses a single comma-separated field).
 */
#[FieldWidgetAction(
  id: 'automator_autocomplete_taxonomy',
  label: new TranslatableMarkup('Automator Taxonomy'),
  widget_types: ['entity_reference_autocomplete'],
  field_types: ['entity_reference'],
  category: new TranslatableMarkup('AI Automators'),
  multiple: FALSE,
)]
class AutoCompleteTaxonomy extends AutomatorBaseAction {

  /**
   * {@inheritdoc}
   */
  protected function transformFormInput(ComplexDataInterface $item): array {
    $entity = $item->entity ?? NULL;
    if (!$entity) {
      return ['target_id' => ''];
    }
    return ['target_id' => $entity->label() . ' (' . $entity->id() . ')'];
  }

}
