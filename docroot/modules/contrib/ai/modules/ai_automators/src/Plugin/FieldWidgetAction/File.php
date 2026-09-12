<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The File action.
 *
 * Uses the base-class AJAX + setFormInput contract. file_generic /
 * file_default widgets wrap Drupal's managed_file element, which reads
 * `fids` (not `target_id`) from user input — so transformFormInput()
 * remaps the field-storage `target_id` to the widget-input `fids` key.
 */
#[FieldWidgetAction(
  id: 'automator_file',
  label: new TranslatableMarkup('Automator File'),
  widget_types: ['file_generic', 'file_default'],
  field_types: ['file'],
)]
class File extends AutomatorBaseAction {

  /**
   * {@inheritdoc}
   */
  protected bool $clearEntity = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function transformFormInput(ComplexDataInterface $item): array {
    $values = parent::transformFormInput($item);
    if (array_key_exists('target_id', $values)) {
      $values['fids'] = $values['target_id'];
      unset($values['target_id']);
    }
    return $values;
  }

}
