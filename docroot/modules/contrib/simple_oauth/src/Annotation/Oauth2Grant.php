<?php

namespace Drupal\simple_oauth\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Defines a OAuth2 Grant item annotation object.
 *
 * @see \Drupal\simple_oauth\Plugin\Oauth2GrantManager
 * @see plugin_api
 *
 * @Annotation
 */
class Oauth2Grant extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public string $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public Translation $label;

}
