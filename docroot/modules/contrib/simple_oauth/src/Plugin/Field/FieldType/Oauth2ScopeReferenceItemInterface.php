<?php

namespace Drupal\simple_oauth\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\simple_oauth\Oauth2ScopeInterface;

/**
 * Defines an interface for the oauth2_scope_reference field item.
 */
interface Oauth2ScopeReferenceItemInterface extends FieldItemInterface {

  /**
   * Get scope object.
   *
   * @return null|\Drupal\simple_oauth\Oauth2ScopeInterface
   *   Return the scope object or NULL if the scope does not exist.
   */
  public function getScope(): ?Oauth2ScopeInterface;

}
