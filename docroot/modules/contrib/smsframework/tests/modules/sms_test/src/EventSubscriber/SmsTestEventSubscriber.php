<?php

declare(strict_types = 1);

namespace Drupal\sms_test\EventSubscriber;

use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\sms\Entity\SmsGateway;
use Drupal\sms\Event\SmsEvents;
use Drupal\sms\Event\RecipientGatewayEvent;

/**
 * Test event subscriber.
 */
final class SmsTestEventSubscriber implements EventSubscriberInterface {

  /**
   * Adds a gateway with ID 'test_gateway_200', with weight '200'.
   *
   * @param \Drupal\sms\Event\RecipientGatewayEvent $event
   *   The RecipientGatewayEvent event.
   */
  public function testAddGateway200(RecipientGatewayEvent $event) {
    if (\Drupal::state()->get('sms_test_event_subscriber__test_gateway_200', FALSE)) {
      $gateway = SmsGateway::load('test_gateway_200');
      $event->addGateway($gateway, 200);
    }
  }

  /**
   * Adds a gateway with ID 'test_gateway_400', with weight '400'.
   *
   * @param \Drupal\sms\Event\RecipientGatewayEvent $event
   *   The RecipientGatewayEvent event.
   */
  public function testAddGateway400(RecipientGatewayEvent $event) {
    if (\Drupal::state()->get('sms_test_event_subscriber__test_gateway_400', FALSE)) {
      $gateway = SmsGateway::load('test_gateway_400');
      $event->addGateway($gateway, 400);
    }
  }

  /**
   * Adds event name to execution order when a message is processed.
   *
   * @param \Symfony\Contracts\EventDispatcher\Event $event
   *   The event.
   * @param string $eventName
   *   The event name.
   */
  public function testExecutionOrder(Event $event, $eventName) {
    $execution_order = \Drupal::state()->get('sms_test_event_subscriber__execution_order', []);
    $execution_order[] = $eventName;
    \Drupal::state()->set('sms_test_event_subscriber__execution_order', $execution_order);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[SmsEvents::MESSAGE_GATEWAY][] = ['testAddGateway200'];
    $events[SmsEvents::MESSAGE_GATEWAY][] = ['testAddGateway400'];

    $events[SmsEvents::MESSAGE_PRE_PROCESS][] = ['testExecutionOrder'];
    $events[SmsEvents::MESSAGE_POST_PROCESS][] = ['testExecutionOrder'];
    $events[SmsEvents::MESSAGE_QUEUE_PRE_PROCESS][] = ['testExecutionOrder'];
    $events[SmsEvents::MESSAGE_QUEUE_POST_PROCESS][] = ['testExecutionOrder'];
    $events[SmsEvents::MESSAGE_INCOMING_PRE_PROCESS][] = ['testExecutionOrder'];
    $events[SmsEvents::MESSAGE_INCOMING_POST_PROCESS][] = ['testExecutionOrder'];
    $events[SmsEvents::MESSAGE_OUTGOING_PRE_PROCESS][] = ['testExecutionOrder'];
    $events[SmsEvents::MESSAGE_OUTGOING_POST_PROCESS][] = ['testExecutionOrder'];

    return $events;
  }

}
