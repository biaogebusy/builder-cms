<?php

namespace Drupal\simple_oauth\Repositories;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Password\PasswordInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Drupal\simple_oauth\Entities\ClientEntity;

class ClientRepository implements ClientRepositoryInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Password\PasswordInterface
   */
  protected $passwordChecker;

  /**
   * Constructs a ClientRepository object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, PasswordInterface $password_checker) {
    $this->entityTypeManager = $entity_type_manager;
    $this->passwordChecker = $password_checker;
  }

  /**
   * {@inheritdoc}
   */
  public function getClientEntity($client_identifier) {
    $client_drupal_entities = $this->entityTypeManager
      ->getStorage('consumer')
      ->loadByProperties(['client_id' => $client_identifier]);

    // Check if the client is registered.
    if (empty($client_drupal_entities)) {
      return NULL;
    }
    /** @var \Drupal\consumers\Entity\ConsumerInterface $client_drupal_entity */
    $client_drupal_entity = reset($client_drupal_entities);

    return new ClientEntity($client_drupal_entity);
  }

  /**
   * @{inheritdoc}
   */
  public function validateClient($client_identifier, $client_secret, $grant_type) {
    if (!$client_entity = $this->getClientEntity($client_identifier)) {
      return FALSE;
    }

    // Get the drupal entity.
    $client_drupal_entity = $client_entity->getDrupalEntity();
    $secret_field = $client_drupal_entity->get('secret');

    // Determine whether the client is public. Note that if a client secret is
    // provided it should be validated, even if the client is non-confidential.
    // The client_credentials grant is specifically special-cased.
    // @see https://datatracker.ietf.org/doc/html/rfc6749#section-4.4
    if (!$client_drupal_entity->get('confidential')->value &&
      $secret_field->isEmpty() &&
      empty($client_secret) &&
      $grant_type !== 'client_credentials') {
      return TRUE;
    }

    // Check if a secret has been provided for this client and validate it.
    // @see https://datatracker.ietf.org/doc/html/rfc6749#section-3.2.1
    return (!$secret_field->isEmpty())
      // The client secret may be NULL; it fails validation without checking.
      ? $client_secret && $this->passwordChecker->check($client_secret, $client_drupal_entity->get('secret')->value)
      : TRUE;
  }

}
