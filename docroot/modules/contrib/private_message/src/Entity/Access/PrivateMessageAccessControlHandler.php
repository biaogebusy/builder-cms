<?php

namespace Drupal\private_message\Entity\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Access control handler for private message entities.
 */
class PrivateMessageAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * The private message service.
   *
   * @var \Drupal\private_message\Service\PrivateMessageServiceInterface
   */
  protected $privateMessageService;

  /**
   * Costructs a PrivateMessageThreadAccessControlHandler entity.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param \Drupal\Core\Plugin\Context\ContextHandlerInterface $context_handler
   *   The context handler service.
   * @param \Drupal\Core\Plugin\Context\ContextRepositoryInterface $context_repository
   *   The context repository service.
   * @param \Drupal\private_message\Service\PrivateMessageServiceInterface $privateMessageService
   *   The private message service.
   */
  public function __construct(EntityTypeInterface $entity_type, ContextHandlerInterface $context_handler, ContextRepositoryInterface $context_repository, PrivateMessageServiceInterface $privateMessageService) {
    parent::__construct($entity_type, $context_handler, $context_repository);

    $this->privateMessageService = $privateMessageService;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('context.handler'),
      $container->get('context.repository'),
      $container->get('private_message.service')
    );
  }

  /**
   * {@inheritdoc}
   *
   * Link the activities to the permissions. checkAccess is called with the
   * $operation as defined in the routing.yml file.
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
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
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'use private messaging system');
  }

}
