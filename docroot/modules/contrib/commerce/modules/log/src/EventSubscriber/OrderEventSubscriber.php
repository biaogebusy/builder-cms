<?php

namespace Drupal\commerce_log\EventSubscriber;

use Drupal\commerce_order\Event\OrderAssignEvent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderEventSubscriber implements EventSubscriberInterface {

  /**
   * The log storage.
   *
   * @var \Drupal\commerce_log\LogStorageInterface
   */
  protected $logStorage;

  /**
   * Constructs a new OrderEventSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->logStorage = $entity_type_manager->getStorage('commerce_log');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      'commerce_order.order.assign' => ['onOrderAssign', -100],
      'commerce_order.post_transition' => ['onOrderPostTransition'],
    ];
  }

  /**
   * Creates a log when an order is assigned.
   *
   * @param \Drupal\commerce_order\Event\OrderAssignEvent $event
   *   The order assign event.
   */
  public function onOrderAssign(OrderAssignEvent $event) {
    $order = $event->getOrder();
    $this->logStorage->generate($order, 'order_assigned', [
      'user' => $event->getCustomer()->getDisplayName(),
    ])->save();
  }

  /**
   * Creates a log on order state update.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function onOrderPostTransition(WorkflowTransitionEvent $event) {
    $transition = $event->getTransition();
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
    $original_state_id = $order->getState()->getOriginalId();
    $original_state = $event->getWorkflow()->getState($original_state_id);

    $this->logStorage->generate($order, 'order_state_updated', [
      'transition_label' => $transition->getLabel(),
      'from_state' => $original_state ? $original_state->getLabel() : $original_state_id,
      'to_state' => $order->getState()->getLabel(),
    ])->save();
  }

}
