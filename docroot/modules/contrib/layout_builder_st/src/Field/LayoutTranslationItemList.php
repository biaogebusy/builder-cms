<?php

namespace Drupal\layout_builder_st\Field;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Session\AccountInterface;

/**
 * A list of layout translation items.
 */
final class LayoutTranslationItemList extends FieldItemList {

  /**
   * Overrides \Drupal\Core\Field\FieldItemListInterface::defaultAccess().
   *
   * @ingroup layout_builder_access
   */
  public function defaultAccess($operation = 'view', ?AccountInterface $account = NULL) {
    // @todo Allow access in https://www.drupal.org/node/2942975.
    return AccessResult::forbidden();
  }

}
