<?php

namespace Drupal\social_api\User;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Manages database related tasks.
 */
abstract class UserManager implements UserManagerInterface {

  /**
   * The Drupal Entity Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * The Drupal logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * The entity type.
   *
   * @var string
   */
  protected string $entityType;

  /**
   * The implementer plugin id.
   *
   * @var string
   */
  protected string $pluginId;

  /**
   * Constructor.
   *
   * @param string $entity_type
   *   The entity table.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Used for loading and creating Social API-related entities.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Used to display messages to user.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Used for logging errors.
   */
  public function __construct(string $entity_type,
                              EntityTypeManagerInterface $entity_type_manager,
                              MessengerInterface $messenger,
                              LoggerChannelFactoryInterface $logger_factory) {

    $this->entityType = $entity_type;
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId(): string {
    return $this->pluginId;
  }

  /**
   * {@inheritdoc}
   */
  public function setPluginId($plugin_id): void {
    $this->pluginId = $plugin_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getDrupalUserId(string $provider_user_id): int|false {
    try {
      $storage = $this->entityTypeManager->getStorage($this->entityType);
      $query = $storage->getQuery();
      $query->accessCheck(FALSE);
      $query->condition('plugin_id', $this->pluginId);
      $query->condition('provider_user_id', $provider_user_id);
      $uids = $query->execute();

      if ($uids) {
        /** @var \Drupal\social_api\Entity\SocialApi[] $user */
        $user = $storage->loadMultiple($uids);
        if (!empty($user)) {
          return current($user)->getUserId();
        }
      }
    }
    catch (\Exception $e) {
      $this->loggerFactory
        ->get($this->getPluginId())
        ->error('Failed to to query entity. Exception: @message', ['@message' => $e->getMessage()]);
    }

    return FALSE;
  }

}
