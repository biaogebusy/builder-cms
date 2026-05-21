<?php

namespace Drupal\simple_oauth\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * Interface for entity reference lists of field items.
 */
interface Oauth2ScopeReferenceItemListInterface extends FieldItemListInterface {

  /**
   * Gets the scopes referenced by this field, preserving field item deltas.
   *
   * @return \Drupal\simple_oauth\Oauth2ScopeInterface[]
   *   An array of scope objects keyed by field item deltas.
   */
  public function getScopes(): array;

}
