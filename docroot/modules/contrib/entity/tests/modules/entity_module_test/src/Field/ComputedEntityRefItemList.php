<?php

namespace Drupal\entity_module_test\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Item list for the computed_entity_ref computed entity reference field.
 */
class ComputedEntityRefItemList extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    // Always make the field value empty.
    $this->list = [];
  }
}
