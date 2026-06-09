<?php

namespace Drupal\key_test\Plugin\KeyProvider;

use Drupal\key\Plugin\KeyProviderBase;
use Drupal\key\KeyInterface;

/**
 * Plugin with deprecated definition entries.
 *
 * @KeyProvider(
 *   id = "deprecated_defintion_entries",
 *   label = @Translation("Deprecated"),
 *   storage_method = "whatever",
 *   key_value = {
 *     "accepted" = FALSE,
 *     "required" = FALSE
 *   }
 * )
 */
class DeprecatedDefinition extends KeyProviderBase {

  /**
   * {@inheritdoc}
   */
  public function getKeyValue(KeyInterface $key) {
    return "deprecatedkey";
  }

}
