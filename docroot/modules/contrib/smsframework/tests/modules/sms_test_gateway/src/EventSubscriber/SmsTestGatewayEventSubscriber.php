<?php

declare(strict_types = 1);

namespace Drupal\sms_test_gateway\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\sms\Event\SmsEvents;
use Drupal\sms\Event\SmsMessageEvent;

/**
 * Test event subscriber.
 */
final class SmsTestGatewayEventSubscriber implements EventSubscriberInterface {

  /**
   * Store incoming messages in memory.
   *
   * @param \Drupal\sms\Event\SmsMessageEvent $event
   *   The event.
   *
   * @see sms_test_gateway_get_incoming()
   */
  public function memoryIncomingMessage(SmsMessageEvent $event) {
    $sms_message = $event->getMessages()[0];

    // Save incoming result for later retrieval.
    $key = 'sms_test_gateway_sms_incoming_preprocess';
    $result = &drupal_static($key);

    if (!is_null($sms_message->getRecipients()) && !is_null($sms_message->getMessage())) {
      $recipients = $sms_message->getRecipients();
      $result['number'] = count($recipients) ? reset($recipients) : NULL;
      $result['message'] = $sms_message->getMessage();
      \Drupal::state()->set($key, $result);
    }

    $key = 'sms_test_gateway.memory.incoming';
    $incoming_messages = &drupal_static($key, []);
    foreach ($event->getMessages() as $message) {
      /** @var \Drupal\sms\Message\SmsMessageInterface $message */
      $incoming_messages[$message->getGateway()->id()][] = $message;
    }
    \Drupal::state()->set($key, $incoming_messages);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[SmsEvents::MESSAGE_INCOMING_POST_PROCESS][] = ['memoryIncomingMessage'];
    return $events;
  }

}
