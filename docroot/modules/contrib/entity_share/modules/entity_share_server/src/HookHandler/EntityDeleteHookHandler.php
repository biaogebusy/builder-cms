<?php

declare(strict_types = 1);

namespace Drupal\entity_share_server\HookHandler;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\RoleInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Hook handler for the entity_delete() hook.
 */
class EntityDeleteHookHandler implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Update channels if needed.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user that has been deleted.
   */
  public function userDelete(UserInterface $user) {
    /** @var \Drupal\entity_share_server\Entity\ChannelInterface[] $channels */
    $channels = $this->entityTypeManager
      ->getStorage('channel')
      ->loadMultiple();
    foreach ($channels as $channel) {
      if ($channel->removeAuthorizedUser($user->uuid())) {
        $channel->save();
      }
    }
  }

  /**
   * Update channels if needed.
   *
   * @param \Drupal\user\RoleInterface $role
   *   The role that has been deleted.
   */
  public function roleDelete(RoleInterface $role) {
    /** @var \Drupal\entity_share_server\Entity\ChannelInterface[] $channels */
    $channels = $this->entityTypeManager
      ->getStorage('channel')
      ->loadMultiple();
    foreach ($channels as $channel) {
      if ($channel->removeAuthorizedRole($role->id())) {
        $channel->save();
      }
    }
  }

}
