<?php

namespace Drupal\simple_oauth\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the OAuth2 Token Type entity.
 *
 * @ConfigEntityType(
 *   id = "oauth2_token_type",
 *   label = @Translation("OAuth2 Token Type"),
 *   handlers = {
 *     "access" = "Drupal\simple_oauth\Entity\Access\LockableConfigEntityAccessControlHandler"
 *   },
 *   config_prefix = "oauth2_token.bundle",
 *   admin_permission = "administer simple_oauth entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "locked",
 *   }
 * )
 */
class Oauth2TokenType extends ConfigEntityBase implements Oauth2TokenTypeInterface {

  use ConfigEntityLockableTrait;

  /**
   * The Access Token Type ID.
   *
   * @var string
   */
  protected string $id;

  /**
   * The Access Token Type label.
   *
   * @var string
   */
  protected string $label;

  /**
   * The Access Token Type label.
   *
   * @var string
   */
  protected string $description = '';

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription(string $description) {
    $this->description = $description;
  }

}
