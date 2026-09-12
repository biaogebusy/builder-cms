<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;
use Drupal\field_widget_actions\Traits\ImageAltTextActionButtonTrait;

/**
 * The Image Filename Rewrite action.
 */
#[FieldWidgetAction(
  id: 'automator_image_filename_rewrite',
  label: new TranslatableMarkup('Automator Image Filename Rewrite'),
  widget_types: ['image_image', 'image_focal_point'],
  field_types: ['image'],
  category: new TranslatableMarkup('AI Automators'),
)]
class ImageFilename extends AutomatorBaseAction {

  use ImageAltTextActionButtonTrait;

  /**
   * {@inheritdoc}
   */
  protected bool $clearEntity = FALSE;

  /**
   * {@inheritdoc}
   *
   * The LlmRewriteImageFilename automator renames the file directly in
   * storeValues(); the target_id in the field value still points to the
   * same (renamed) file. Map target_id to the widget's 'fids' property so
   * the rebuilt widget reloads the file and refreshes its preview.
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
