<?php

namespace Drupal\simple_oauth\Plugin;

use Drupal\Component\Plugin\PluginBase;

/**
 * Base class for OAuth2 Grant plugins.
 */
abstract class Oauth2GrantBase extends PluginBase implements Oauth2GrantInterface {

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return $this->pluginDefinition['label'];
  }

}
