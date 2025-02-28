<?php

namespace Drupal\entity\QueryAccess;

use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Generic query access handler for entity types that do not have one.
 *
 * This query access handler only fires the alter event so that modules can
 * subscribe to the query access alter event to alter any entity query or views
 * query without having to duplicate the related code from Entity API.
 */
final class EventOnlyQueryAccessHandler implements EntityHandlerInterface, QueryAccessHandlerInterface {

  /**
   * The entity type.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityType;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a new EventOnlyQueryAccessHandler object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(EntityTypeInterface $entity_type, EventDispatcherInterface $event_dispatcher, AccountInterface $current_user) {
    $this->entityType = $entity_type;
    $this->eventDispatcher = $event_dispatcher;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('event_dispatcher'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConditions($operation, AccountInterface $account = NULL) {
    $account = $account ?: $this->currentUser;
    $entity_type_id = $this->entityType->id();
    $conditions = new ConditionGroup('OR');

    // Allow other modules to modify the conditions before they are used.
    $event = new QueryAccessEvent($conditions, $operation, $account, $entity_type_id);
    $this->eventDispatcher->dispatch($event, 'entity.query_access');
    $this->eventDispatcher->dispatch($event, "entity.query_access.{$entity_type_id}");

    return $conditions;
  }

}
