<?php

namespace Drupal\commerce_product\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\config_translation\Access\ConfigTranslationOverviewAccess;
use Drupal\config_translation\ConfigMapperInterface;

/**
 * Checks access for displaying the product attribute translation overview.
 */
class ProductAttributeTranslationAccessCheck extends ConfigTranslationOverviewAccess {

  /**
   * {@inheritdoc}
   */
  protected function doCheckAccess(AccountInterface $account, ConfigMapperInterface $mapper, $source_language = NULL) {
    $permission_access = $account->hasPermission('translate commerce_product_attribute') ||
      $account->hasPermission('translate configuration');

    $access =
      $permission_access &&
      $mapper->hasSchema() &&
      $mapper->hasTranslatable() &&
      (!$source_language || !$source_language->isLocked());

    return AccessResult::allowedIf($access)->cachePerPermissions();
  }

}
