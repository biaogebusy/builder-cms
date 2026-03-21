<?php

namespace Drupal\simple_oauth\Entities;

use Drupal\consumers\Entity\ConsumerInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;

class ClientEntity implements ClientEntityInterface {

  use EntityTrait, ClientTrait;

  /**
   * The consumer entity.
   *
   * @var \Drupal\consumers\Entity\ConsumerInterface
   */
  protected $entity;

  /**
   * ClientEntity constructor.
   *
   * @param \Drupal\consumers\Entity\ConsumerInterface $entity
   *   The Drupal entity.
   */
  public function __construct(ConsumerInterface $entity) {
    $this->entity = $entity;
    $this->setIdentifier($entity->getClientId());
    $this->setName($entity->label());
    if ($entity->hasField('redirect')) {
      $this->redirectUri = $entity->get('redirect')->value;
    }
    if ($entity->hasField('confidential')) {
      $this->isConfidential = (bool) $entity->get('confidential')->value;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->name = $name;
  }

  /**
   * {@inheritdoc}
   */
  public function getDrupalEntity() {
    return $this->entity;
  }

}
