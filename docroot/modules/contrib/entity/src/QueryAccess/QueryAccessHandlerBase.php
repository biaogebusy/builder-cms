<?php

namespace Drupal\entity\QueryAccess;

use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\EntityOwnerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides common logic for query access handlers.
 *
 * @see \Drupal\entity\QueryAccess\QueryAccessHandler
 * @see \Drupal\entity\QueryAccess\UncacheableQueryAccessHandler
 */
abstract class QueryAccessHandlerBase implements EntityHandlerInterface, QueryAccessHandlerInterface {

  /**
   * The entity type.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityType;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

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
   * Constructs a new QueryAccessHandlerBase object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_info
   *   The entity type bundle info.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityTypeBundleInfoInterface $bundle_info, EventDispatcherInterface $event_dispatcher, AccountInterface $current_user) {
    $this->entityType = $entity_type;
    $this->bundleInfo = $bundle_info;
    $this->eventDispatcher = $event_dispatcher;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.bundle.info'),
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
    $conditions = $this->buildConditions($operation, $account);

    // Allow other modules to modify the conditions before they are used.
    $event = new QueryAccessEvent($conditions, $operation, $account, $entity_type_id);
    $this->eventDispatcher->dispatch($event, 'entity.query_access');
    $this->eventDispatcher->dispatch($event, "entity.query_access.{$entity_type_id}");

    return $conditions;
  }

  /**
   * Builds the conditions for the given operation and user.
   *
   * @param string $operation
   *   The access operation. Usually one of "view", "update", "duplicate",
   *   or "delete".
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to restrict access.
   *
   * @return \Drupal\entity\QueryAccess\ConditionGroup
   *   The conditions.
   */
  public function buildConditions($operation, AccountInterface $account) {
    $entity_type_id = $this->entityType->id();
    $has_owner = $this->entityType->entityClassImplements(EntityOwnerInterface::class);
    $has_published = $this->entityType->entityClassImplements(EntityPublishedInterface::class);
    // Guard against broken/incomplete entity type definitions.
    if ($has_owner && !$this->entityType->hasKey('owner') && !$this->entityType->hasKey('uid')) {
      throw new \RuntimeException(sprintf('The "%s" entity type did not define an "owner" or "uid" key.', $entity_type_id));
    }
    if ($has_published && !$this->entityType->hasKey('published')) {
      throw new \RuntimeException(sprintf('The "%s" entity type did not define a "published" key', $entity_type_id));
    }

    $admin_permission = $this->entityType->getAdminPermission() ?: "administer {$entity_type_id}";
    if ($account->hasPermission($admin_permission)) {
      // The user has full access to all operations, no conditions needed.
      $conditions = new ConditionGroup('OR');
      $conditions->addCacheContexts(['user.permissions']);
      return $conditions;
    }

    if ($has_owner) {
      $entity_conditions = $this->buildEntityOwnerConditions($operation, $account);
    }
    else {
      $entity_conditions = $this->buildEntityConditions($operation, $account);
    }

    $conditions = NULL;
    if ($operation == 'view' && $has_published) {
      $owner_key = $this->entityType->hasKey('owner') ? $this->entityType->getKey('owner') : $this->entityType->getKey('uid');
      $published_key = $this->entityType->getKey('published');
      $published_conditions = NULL;
      $unpublished_conditions = NULL;

      if ($entity_conditions) {
        // Restrict the existing conditions to published entities only.
        $published_conditions = new ConditionGroup('AND');
        $published_conditions->addCacheContexts(['user.permissions']);
        $published_conditions->addCondition($entity_conditions);
        $published_conditions->addCondition($published_key, '1');
      }
      if ($has_owner && $account->hasPermission("view own unpublished $entity_type_id")) {
        $unpublished_conditions = new ConditionGroup('AND');
        $unpublished_conditions->addCacheContexts(['user']);
        $unpublished_conditions->addCondition($owner_key, $account->id());
        $unpublished_conditions->addCondition($published_key, '0');
      }

      if ($published_conditions && $unpublished_conditions) {
        $conditions = new ConditionGroup('OR');
        $conditions->addCondition($published_conditions);
        $conditions->addCondition($unpublished_conditions);
      }
      elseif ($published_conditions) {
        $conditions = $published_conditions;
      }
      elseif ($unpublished_conditions) {
        $conditions = $unpublished_conditions;
      }
    }
    else {
      $conditions = $entity_conditions;
    }

    if (!$conditions) {
      // The user doesn't have access to any entities.
      // Falsify the query to ensure no results are returned.
      $conditions = new ConditionGroup('OR');
      $conditions->addCacheContexts(['user.permissions']);
      $conditions->alwaysFalse();
    }

    return $conditions;
  }

  /**
   * Builds the conditions for entities that have an owner.
   *
   * @param string $operation
   *   The access operation. Usually one of "view", "update", "duplicate",
   *   or "delete".
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to restrict access.
   *
   * @return \Drupal\entity\QueryAccess\ConditionGroup|null
   *   The conditions, or NULL if the user doesn't have access to any entity.
   */
  protected function buildEntityOwnerConditions($operation, AccountInterface $account) {
    $entity_type_id = $this->entityType->id();
    $owner_key = $this->entityType->hasKey('owner') ? $this->entityType->getKey('owner') : $this->entityType->getKey('uid');
    $bundle_key = $this->entityType->getKey('bundle');

    $conditions = new ConditionGroup('OR');
    $conditions->addCacheContexts(['user.permissions']);
    // Any $entity_type permission.
    if ($account->hasPermission("$operation any $entity_type_id")) {
      // The user has full access, no conditions needed.
      return $conditions;
    }

    // Own $entity_type permission.
    if ($account->hasPermission("$operation own $entity_type_id")) {
      $conditions->addCacheContexts(['user']);
      $conditions->addCondition($owner_key, $account->id());
    }

    $bundles = array_keys($this->bundleInfo->getBundleInfo($entity_type_id));
    $bundles_with_any_permission = [];
    $bundles_with_own_permission = [];
    foreach ($bundles as $bundle) {
      if ($account->hasPermission("$operation any $bundle $entity_type_id")) {
        $bundles_with_any_permission[] = $bundle;
      }
      if ($account->hasPermission("$operation own $bundle $entity_type_id")) {
        $bundles_with_own_permission[] = $bundle;
      }
    }
    // Any $bundle permission.
    if ($bundles_with_any_permission) {
      $conditions->addCondition($bundle_key, $bundles_with_any_permission);
    }
    // Own $bundle permission.
    if ($bundles_with_own_permission) {
      $conditions->addCacheContexts(['user']);
      $conditions->addCondition((new ConditionGroup('AND'))
        ->addCondition($owner_key, $account->id())
        ->addCondition($bundle_key, $bundles_with_own_permission)
      );
    }

    return $conditions->count() ? $conditions : NULL;
  }

  /**
   * Builds the conditions for entities that do not have an owner.
   *
   * @param string $operation
   *   The access operation. Usually one of "view", "update", "duplicate",
   *   or "delete".
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to restrict access.
   *
   * @return \Drupal\entity\QueryAccess\ConditionGroup|null
   *   The conditions, or NULL if the user doesn't have access to any entity.
   */
  protected function buildEntityConditions($operation, AccountInterface $account) {
    $entity_type_id = $this->entityType->id();
    $bundle_key = $this->entityType->getKey('bundle');

    $conditions = new ConditionGroup('OR');
    $conditions->addCacheContexts(['user.permissions']);
    // The $entity_type permission.
    if ($account->hasPermission("$operation $entity_type_id")) {
      // The user has full access, no conditions needed.
      return $conditions;
    }

    $bundles = array_keys($this->bundleInfo->getBundleInfo($entity_type_id));
    $bundles_with_any_permission = [];
    foreach ($bundles as $bundle) {
      if ($account->hasPermission("$operation $bundle $entity_type_id")) {
        $bundles_with_any_permission[] = $bundle;
      }
    }
    // The $bundle permission.
    if ($bundles_with_any_permission) {
      $conditions->addCondition($bundle_key, $bundles_with_any_permission);
    }

    return $conditions->count() ? $conditions : NULL;
  }

}
