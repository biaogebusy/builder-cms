<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Service;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\key\Entity\Key;
use Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationInterface;
use Drupal\key\KeyRepositoryInterface;

/**
 * Abstraction layer to support local storage and Key module.
 *
 * @package Drupal\entity_share_client\Service
 */
class KeyProvider {

  /**
   * Key module service conditionally injected.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected $keyRepository;

  /**
   * The key value store to use.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $keyValueStore;

  /**
   * KeyService constructor.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
   *   The key value store to use.
   */
  public function __construct(KeyValueFactoryInterface $key_value_factory) {
    $this->keyValueStore = $key_value_factory->get(ClientAuthorizationInterface::LOCAL_STORAGE_KEY_VALUE_COLLECTION);
  }

  /**
   * Provides a means to our services.yml file to conditionally inject service.
   *
   * @param \Drupal\key\KeyRepositoryInterface $repository
   *   The injected service, if it exists.
   */
  public function setKeyRepository(KeyRepositoryInterface $repository) {
    $this->keyRepository = $repository;
  }

  /**
   * Detects if key module service was injected.
   *
   * @return bool
   *   True if the KeyRepository is present.
   */
  public function additionalProviders() {
    return $this->keyRepository instanceof KeyRepositoryInterface;
  }

  /**
   * Get the provided credentials.
   *
   * @param \Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationInterface $plugin
   *   An authorization plugin.
   *
   * @return array|string
   *   The value of the configured key.
   */
  public function getCredentials(ClientAuthorizationInterface $plugin) {
    $provider = $plugin->getCredentialProvider();
    $credentials = [];
    if (empty($provider)) {
      return $credentials;
    }
    switch ($provider) {
      case 'key':
        $keyEntity = $this->keyRepository->getKey($plugin->getStorageKey());
        if ($keyEntity instanceof Key) {
          // A key was found in the repository.
          $credentials = $keyEntity->getKeyValues();
        }
        break;

      default:
        $credentials = $this->keyValueStore->get($plugin->getStorageKey());
    }

    return $credentials;
  }

}
