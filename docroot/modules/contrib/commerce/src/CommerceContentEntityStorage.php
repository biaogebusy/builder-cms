<?php

namespace Drupal\commerce;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The default Commerce storage for content entities.
 *
 * Fires matching events for entity hooks.
 */
class CommerceContentEntityStorage extends SqlContentEntityStorage {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->eventDispatcher = $container->get('event_dispatcher');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function postLoad(array &$entities) {
    parent::postLoad($entities);

    $event_class = $this->entityType->getHandlerClass('event');
    if (!$event_class) {
      return;
    }
    // hook_entity_load() is invoked for all entities at once.
    // The event is dispatched for each entity separately, for better DX.
    // @todo Evaluate performance implications.
    $event_name = $this->getEventName('load');
    foreach ($entities as $entity) {
      $this->eventDispatcher->dispatch(new $event_class($entity), $event_name);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function invokeHook($hook, EntityInterface $entity) {
    parent::invokeHook($hook, $entity);

    $event_class = $this->entityType->getHandlerClass('event');
    if ($event_class) {
      $this->eventDispatcher->dispatch(new $event_class($entity), $this->getEventName($hook));
    }
  }

  /**
   * Gets the event name for the given hook.
   *
   * Created using the entity type's module name and ID.
   * For example, the 'presave' hook for commerce_order_item entities maps
   * to the 'commerce_order.commerce_order_item.presave' event name.
   *
   * @param string $hook
   *   One of 'load', 'create', 'presave', 'insert', 'update', 'predelete',
   *   'delete', 'translation_insert', 'translation_delete'.
   *
   * @return string
   *   The event name.
   */
  protected function getEventName($hook) {
    return $this->entityType->getProvider() . '.' . $this->entityType->id() . '.' . $hook;
  }

}
