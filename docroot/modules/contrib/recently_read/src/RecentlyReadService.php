<?php

namespace Drupal\recently_read;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionManager;

/**
 * Recently read service.
 */
class RecentlyReadService implements RecentlyReadServiceInterface {

  /**
   * The current user injected into the service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * SessionManager service.
   *
   * @var \Drupal\Core\Session\SessionManager
   */
  protected $sessionManager;

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The Recently Read storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $recentlyReadStorage;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\Core\Session\SessionManager $sessionManager
   *   Session manager.
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   Config factory service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    AccountInterface $current_user,
    EntityTypeManagerInterface $entity_type_manager,
    SessionManager $sessionManager,
    ConfigFactory $configFactory
  ) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->sessionManager = $sessionManager;
    $this->configFactory = $configFactory;
    $this->recentlyReadStorage = $this->entityTypeManager->getStorage('recently_read');
  }

  /**
   * {@inheritdoc}
   */
  public function insertEntity(EntityInterface $entity, AccountInterface $user = NULL) {
    // Get configuration and check if RR delete options is count based.
    $config = $this->configFactory->getEditable('recently_read.configuration');
    $maxRecords = NULL;
    if ($config->get('delete_config') == "count") {
      $maxRecords = $config->get('count');
    }

    // Assert a user.
    $user = $user ?? $this->currentUser;

    // If anonymous set user_id to 0 and check for any existing entries.
    if ($user->isAnonymous()) {
      // Ensure something is in $_SESSION, otherwise the session ID will
      // not persist.
      // TODO: Replace this with something cleaner once core provides it.
      // See https://www.drupal.org/node/2865991.
      if (!isset($_SESSION['recently_read'])) {
        $_SESSION['recently_read'] = TRUE;
        $this->sessionManager->start();
      }
      $exists = $this->recentlyReadStorage->loadByProperties([
        'session_id' => $this->sessionManager->getId(),
        'type' => $entity->getEntityTypeId(),
        'entity_id' => $entity->id(),
      ]);
    }
    else {
      $exists = $this->recentlyReadStorage->loadByProperties([
        'user_id' => $user->id(),
        'type' => $entity->getEntityTypeId(),
        'entity_id' => $entity->id(),
      ]);
    }
    // If exists then update created else create new.
    if (!empty($exists)) {
      foreach ($exists as $entry) {
        $entry->setCreatedTime(time())->save();
      }
    }
    else {
      // Create new.
      $recentlyRead = $this->recentlyReadStorage->create([
        'type' => $entity->getEntityTypeId(),
        'user_id' => $user->id(),
        'entity_id' => $entity->id(),
        'session_id' => $user->id() ? 0 : $this->sessionManager->getId(),
        'created' => time(),
      ]);
      $recentlyRead->save();
    }
    // Delete records if there is a limit.
    $userRecords = $this->getRecords($user->id());
    if ($maxRecords && count($userRecords) > $maxRecords) {
      $records = array_slice($userRecords, $maxRecords, count($userRecords));
      $this->deleteRecords($records);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteRecords(array $records) {
    foreach ($records as $rid) {
      // Delete data.
      $recently_read = $this->recentlyReadStorage->load($rid);
      $recently_read->delete();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getRecords($user_id) {
    if ($user_id != 0) {
      $records = $this->recentlyReadStorage->getQuery()
        ->condition('user_id', $user_id)
        ->sort('created', 'DESC')
        ->execute();
    }
    else {
      $records = $this->recentlyReadStorage->getQuery()
        ->condition('session_id', session_id())
        ->sort('created', 'DESC')
        ->execute();
    }
    return $records;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteEntityRecords(EntityInterface $entity, AccountInterface $user = NULL) {
    $properties = [
      'type' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
    ];
    if ($user) {
      if ($user->isAnonymous()) {
        $properties['session_id'] = $this->sessionManager->getId();
      }
      else {
        $properties['user_id'] = $user->id();
      }
    }

    $recently_read_entities = $this->recentlyReadStorage->loadByProperties($properties);
    $this->recentlyReadStorage->delete($recently_read_entities);
  }

}
