<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The Text to Image action.
 */
#[FieldWidgetAction(
  id: 'text_to_image',
  label: new TranslatableMarkup('Text to Image'),
  widget_types: ['image_image'],
  field_types: ['image'],
)]
class TextToImage extends AutomatorBaseAction {

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
