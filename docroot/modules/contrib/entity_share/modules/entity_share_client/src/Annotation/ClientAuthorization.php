<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an Entity Share Client authorization annotation object.
 *
 * The Entity Share Client must be authorized to pull entities from the
 * Entity Share Server offering the content. Such authorization could be
 * by authenticating as an authorized user or it could be by presenting
 * some previously created proof of authorization, such as an OAuth2 token.
 *
 * @see \Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationManager
 * @see plugin_api
 *
 * @Annotation
 */
class ClientAuthorization extends Plugin {

  /**
   * The plugin ID.
   *
   * A machine name for the authorization type provided by this plugin.
   *
   * @var string
   */
  public $id;

  /**
   * A human readable name for the authorization type provided by this plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

}
