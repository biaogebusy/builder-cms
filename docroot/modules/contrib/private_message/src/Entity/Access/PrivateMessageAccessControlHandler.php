<?php

declare(strict_types=1);

namespace Drupal\private_message\Entity\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Access control handler for private message entities.
 */
class PrivateMessageAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  public function __construct(
    EntityTypeInterface $entity_type,
    protected readonly PrivateMessageServiceInterface $privateMessageService,
  ) {
    parent::__construct($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): self {
    return new static(
      $entity_type,
      $container->get('private_message.service')
    );
  }

  /**
   * {@inheritdoc}
   *
   * Link the activities to the permissions. checkAccess is called with the
   * $operation as defined in the routing.yml file.
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    assert($entity instanceof PrivateMessageInterface);

    if ($account->hasPermission('use private messaging system')) {
      switch ($operation) {
        case 'view':
          if ($entity->getOwnerId() == $account->id()) {
            return AccessResult::allowed();
          }

          $private_message_thread = $this->privateMessageService->getThreadFromMessage($entity);
          if ($private_message_thread->isMember($account->id())) {
            return AccessResult::allowed();
          }

          if ($account->hasPermission('administer private messages')) {
            return AccessResult::allowed();
          }
          break;

        case 'delete':
          if ($entity->getOwnerId() == $account->id()) {
            return AccessResult::allowedIfHasPermissions($account,
              [
                'delete own private message',
                'delete any private message',
              ], 'OR');
          }

          return AccessResult::allowedIfHasPermission($account, 'delete any private message');
      }
    }

    return AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   *
   * Separate from the checkAccess because the entity does not yet exist, it
   * will be created during the 'add' process.
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'use private messaging system');
  }

}
