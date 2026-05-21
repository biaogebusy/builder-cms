<?php

namespace Drupal\simple_oauth_static_scope\Plugin\ScopeProvider;

use Drupal\simple_oauth\Plugin\ScopeProviderBase;

/**
 * The Static scope provider.
 *
 * @ScopeProvider(
 *   id = "static",
 *   label = @Translation("Static (YAML)"),
 *   adapter_class = "Drupal\simple_oauth_static_scope\Plugin\Oauth2ScopePluginAdapter"
 * )
 */
class StaticScopeProvider extends ScopeProviderBase {}
