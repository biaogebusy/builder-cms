<?php

namespace Drupal\consumers_test;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Test computed field for client id.
 */
class ConsumerClientIDTestField extends FieldItemList {
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $value = '';
    $node = $this->getEntity();
    $consumer = \Drupal::service('consumer.negotiator')->negotiateFromRequest();

    if ($node->bundle() === CONSUMERS_TEST_NODE_TYPE && $consumer) {
      $value = $consumer->getClientId();
    }

    $this->list[0] = $this->createItem(0, $value);
  }

}
