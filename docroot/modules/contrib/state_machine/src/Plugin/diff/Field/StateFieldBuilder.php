<?php

namespace Drupal\state_machine\Plugin\diff\Field;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\diff\Plugin\diff\Field\CoreFieldBuilder;

/**
 * Plugin to compare state fields.
 *
 * @FieldDiffBuilder(
 *   id = "state_diff_builder",
 *   label = @Translation("State Field Diff"),
 *   field_types = {
 *     "state"
 *   },
 * )
 */
class StateFieldBuilder extends CoreFieldBuilder {

  /**
   * {@inheritdoc}
   */
  public function build(FieldItemListInterface $field_items) {
    $result = [];

    foreach ($field_items as $field_key => $field_item) {
      if (!$field_item->isEmpty()) {
        $value = $field_item->view(['label' => 'hidden', 'type' => 'default']);
        $rendered_value = $this->renderer->renderPlain($value);
        $result[$field_key][] = $rendered_value;
      }
    }

    return $result;
  }

}
