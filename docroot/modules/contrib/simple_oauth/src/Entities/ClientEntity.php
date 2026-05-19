<?php

namespace Drupal\simple_oauth\Entities;

use Drupal\consumers\Entity\Consumer;
use League\OAuth2\Server\Entities\Traits\ClientTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;

/**
 * The client entity.
 */
class ClientEntity implements ClientEntityInterface {

  use EntityTrait, ClientTrait;

  /**
   * The consumer entity.
   *
   * @var \Drupal\consumers\Entity\Consumer
   */
  protected Consumer $entity;

  /**
   * ClientEntity constructor.
   *
   * @param \Drupal\consumers\Entity\Consumer $entity
   *   The Drupal entity.
   */
  public function __construct(Consumer $entity) {
    $this->entity = $entity;
    $this->setIdentifier($entity->getClientId());
    $this->setName($entity->label());
    $this->setRedirectUri($entity);
    $this->isConfidential = (bool) $entity->get('confidential')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name): void {
    $this->name = $name;
  }

  /**
   * {@inheritdoc}
   */
  public function getDrupalEntity(): Consumer {
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setRedirectUri(Consumer $entity): void {
    $redirect_uri = [];
    foreach ($entity->get('redirect')->getValue() as $redirect) {
      $redirect_uri[] = $redirect['value'];
    }
    $this->redirectUri = $redirect_uri;
  }

}
