<?php

namespace Drupal\simple_oauth\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemList;

/**
 * Defines an item list class for OAuth2 Scope reference fields.
 */
class Oauth2ScopeReferenceItemList extends FieldItemList implements Oauth2ScopeReferenceItemListInterface {

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraints = parent::getConstraints();
    $constraint_manager = $this->getTypedDataManager()->getValidationConstraintManager();
    $constraints[] = $constraint_manager->create('Oauth2ScopeReference', []);
    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public function getScopes(): array {
    if (empty($this->list)) {
      return [];
    }

    $scopes = $ids = [];
    foreach ($this->list as $delta => $item) {
      $ids[$delta] = $item->scope_id;
    }

    /** @var \Drupal\simple_oauth\Oauth2ScopeAdapterInterface $scope_provider */
    $scope_provider = \Drupal::service('simple_oauth.oauth2_scope.provider');
    $loaded_scopes = $scope_provider->loadMultiple($ids);

    foreach ($ids as $delta => $scope_id) {
      if (isset($loaded_scopes[$scope_id])) {
        $scopes[$delta] = $loaded_scopes[$scope_id];
      }
    }
    // Ensure the returned array is ordered by deltas.
    ksort($scopes);

    return $scopes;
  }

}
