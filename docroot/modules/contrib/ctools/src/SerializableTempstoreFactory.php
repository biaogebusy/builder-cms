<?php

namespace Drupal\ctools;

use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\TempStore\SharedTempStore as CoreSharedTempStore;

/**
 * A factory for creating SerializableTempStore objects.
 *
 * @deprecated in ctools 8.x-3.10. Will be removed before ctools:4.0.0.
 *   Use \Drupal\Core\TempStore\SharedTempStoreFactory instead.
 */
class SerializableTempstoreFactory extends SharedTempStoreFactory {

  /**
   * The current logged user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a Drupal\Core\TempStore\SharedTempStoreFactory object.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $storage_factory
   *   The key/value store factory.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock_backend
   *   The lock object used for this data.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param int $expire
   *   The time to live for items, in seconds.
   * @param \Drupal\Core\Session\AccountProxyInterface|null $current_user
   *   The current logged user.
   */
  public function __construct(KeyValueExpirableFactoryInterface $storage_factory, LockBackendInterface $lock_backend, RequestStack $request_stack, $expire = 604800, AccountProxyInterface $current_user = NULL) {
    parent::__construct($storage_factory, $lock_backend, $request_stack, $expire);
    $this->currentUser = $current_user ?: \Drupal::currentUser();
  }

  /**
   * {@inheritdoc}
   */
  public function get($collection, $owner = NULL) {
    // Use the currently authenticated user ID or the active user ID unless the
    // owner is overridden.
    if (!isset($owner)) {
      $owner = $this->currentUser->id() ?: session_id();
    }

    // Store the data for this collection in the database.
    $storage = $this->storageFactory->get("tempstore.shared.$collection");
    return new CoreSharedTempStore($storage, $this->lockBackend, $owner, $this->requestStack, $this->expire);
  }

}
