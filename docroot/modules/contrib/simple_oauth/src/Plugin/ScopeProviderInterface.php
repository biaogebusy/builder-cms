<?php

namespace Drupal\simple_oauth\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\simple_oauth\Oauth2ScopeAdapterInterface;

/**
 * Defines an interface for Scope Provider plugins.
 */
interface ScopeProviderInterface extends PluginInspectionInterface {

  /**
   * Get the scope provider label.
   *
   * @return string
   *   Returns the plugin label.
   */
  public function label(): string;

  /**
   * Get the scope provider adapter.
   *
   * @return \Drupal\simple_oauth\Oauth2ScopeAdapterInterface
   *   Returns the scope provider class.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getScopeProviderAdapter(): Oauth2ScopeAdapterInterface;

}
