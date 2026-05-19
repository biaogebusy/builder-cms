<?php

namespace Drupal\simple_oauth\Plugin\ScopeProvider;

use Drupal\simple_oauth\Plugin\ScopeProviderBase;

/**
 * The Dynamic scope provider.
 *
 * @ScopeProvider(
 *   id = "dynamic",
 *   label = @Translation("Dynamic (entity)"),
 *   adapter_class = "Drupal\simple_oauth\Entity\Oauth2ScopeEntityAdapter"
 * )
 */
class DynamicScopeProvider extends ScopeProviderBase {}
