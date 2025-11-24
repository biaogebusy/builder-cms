<?php

namespace Drupal\commerce_product\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\config_translation\Access\ConfigTranslationFormAccess;
use Drupal\config_translation\ConfigMapperInterface;
use Drupal\config_translation\ConfigMapperManagerInterface;

/**
 * Checks access for displaying the product attribute add, edit, delete forms.
 */
class ProductAttributeTranslationFormAccessCheck extends ConfigTranslationFormAccess {

  /**
   * Constructs a new ProductAttributeTranslationFormAccessCheck object.
   *
   * @param \Drupal\config_translation\ConfigMapperManagerInterface $config_mapper_manager
   *   The mapper plugin discovery service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   * @param \Drupal\commerce_product\Access\ProductAttributeTranslationAccessCheck $translationAccessCheck
   *   The main access check service.
   */
  public function __construct(ConfigMapperManagerInterface $config_mapper_manager, LanguageManagerInterface $language_manager, protected ProductAttributeTranslationAccessCheck $translationAccessCheck) {
    parent::__construct($config_mapper_manager, $language_manager);
  }

  /**
   * {@inheritdoc}
   */
  protected function doCheckAccess(AccountInterface $account, ConfigMapperInterface $mapper, $source_language = NULL, $target_language = NULL) {
    $base_access_result = $this->translationAccessCheck->doCheckAccess($account, $mapper, $source_language);

    $access =
      $target_language &&
      !$target_language->isLocked() &&
      (!$source_language || ($target_language->getId() !== $source_language->getId()));

    return $base_access_result->andIf(AccessResult::allowedIf($access));
  }

}
