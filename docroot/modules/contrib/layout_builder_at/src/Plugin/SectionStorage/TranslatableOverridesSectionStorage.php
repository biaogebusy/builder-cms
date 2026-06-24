<?php

namespace Drupal\layout_builder_at\Plugin\SectionStorage;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;

/**
 * Provides translatable overrides for section storage.
 *
 * This class extends OverridesSectionStorage to support translations
 * in layout overrides, ensuring correct language handling.
 */
class TranslatableOverridesSectionStorage extends OverridesSectionStorage {

  /**
   * {@inheritdoc}
   */
  protected function handleTranslationAccess(AccessResult $result, $operation, AccountInterface $account): AccessResultInterface {
    return $result;
  }

}
