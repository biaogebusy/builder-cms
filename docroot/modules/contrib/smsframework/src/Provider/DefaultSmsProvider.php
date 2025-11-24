<?php

declare(strict_types = 1);

namespace Drupal\sms\Provider;

use Drupal\sms\Event\SmsDeliveryReportEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\sms\Entity\SmsMessage;
use Drupal\sms\Entity\SmsGatewayInterface;
use Drupal\sms\Entity\SmsMessageInterface as SmsMessageEntityInterface;
use Drupal\sms\Event\SmsMessageEvent;
use Drupal\sms\Message\SmsMessageInterface;
use Drupal\sms\Plugin\SmsGateway\SmsIncomingEventProcessorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\sms\Exception\SmsException;
use Drupal\sms\Exception\SmsDirectionException;
use Drupal\sms\Direction;
use Drupal\sms\Event\SmsEvents;

/**
 * The SMS provider that provides default messaging functionality.
 */
class DefaultSmsProvider implements SmsProviderInterface {

  /**
   * Creates a new instance of the default SMS provider.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(
    protected EventDispatcherInterface $eventDispatcher,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function queue(SmsMessageInterface $sms_message) {
    if (!$sms_message->getDirection()) {
      throw new SmsDirectionException('Missing direction for message.');
    }

    $sms_messages = $this->dispatchEvent(SmsEvents::MESSAGE_PRE_PROCESS, [$sms_message])->getMessages();
    $sms_messages = $this->dispatchEvent(SmsEvents::MESSAGE_QUEUE_PRE_PROCESS, $sms_messages)->getMessages();

    foreach ($sms_messages as $gateway_id => &$sms_message) {
      // Tag so SmsEvents::MESSAGE_PRE_PROCESS is not dispatched again.
      $sms_message->setOption('_skip_preprocess_event', TRUE);

      // Validate SMS message entities.
      if ($sms_message instanceof SmsMessageEntityInterface) {
        $errors = [];
        $violations = $sms_message->validate();
        foreach ($violations->getFieldNames() as $field_name) {
          foreach ($violations->getByField($field_name) as $violation) {
            $errors[] = "[$field_name]: " . strip_tags((string) $violation->getMessage());
          }
        }

        if ($errors) {
          throw new SmsException(sprintf('Can not queue SMS message because there are %s validation error(s): %s', count($errors), implode(' ', $errors)));
        }
      }

      if ($sms_message->getGateway()->getSkipQueue()) {
        switch ($sms_message->getDirection()) {
          case Direction::INCOMING:
            $this->incoming($sms_message);
            break;

          case Direction::OUTGOING:
            $this->send($sms_message);
            break;
        }
        continue;
      }

      $sms_message = SmsMessage::convertFromSmsMessage($sms_message);
      $sms_message->save();
    }

    // Queue has different post-process events because there is no result.
    return $this->dispatchEvent(SmsEvents::MESSAGE_QUEUE_POST_PROCESS, $sms_messages)->getMessages();
  }

  /**
   * {@inheritdoc}
   */
  public function send(SmsMessageInterface $sms) {
    $sms->setDirection(Direction::OUTGOING);

    $dispatch = !$sms->getOption('_skip_preprocess_event');
    $sms_messages = $dispatch ? $this->dispatchEvent(SmsEvents::MESSAGE_PRE_PROCESS, [$sms])->getMessages() : [$sms];
    $sms_messages = $this->dispatchEvent(SmsEvents::MESSAGE_OUTGOING_PRE_PROCESS, $sms_messages)->getMessages();

    // Iterate over messages individually since pre-process can modify the
    // gateway used.
    foreach ($sms_messages as $sms_message) {
      $plugin = $sms_message->getGateway()->getPlugin();

      $result = $plugin->send($sms_message);
      if ($result === NULL) {
        // @codingStandardsIgnoreLine
        @trigger_error('Gateway plugins returning NULL is deprecated in smsframework:2.1.0 and will be removed in smsframework:3.0.0. The contract for \Drupal\sms\Plugin\SmsGatewayPluginInterface::send does not permit returning NULL. See https://www.drupal.org/node/3262679', E_USER_DEPRECATED);
      }

      $sms_message->setResult($result);

      $this->dispatchEvent(SmsEvents::MESSAGE_OUTGOING_POST_PROCESS, [$sms_message]);
      $this->dispatchEvent(SmsEvents::MESSAGE_POST_PROCESS, [$sms_message]);
    }

    return $sms_messages;
  }

  /**
   * {@inheritdoc}
   */
  public function incoming(SmsMessageInterface $sms_message) {
    $sms_message->setDirection(Direction::INCOMING);

    // Do not iterate over messages individually like outgoing, changing gateway
    // in pre-process events do not apply to incoming.
    $plugin = $sms_message->getGateway()->getPlugin();

    $dispatch = !$sms_message->getOption('_skip_preprocess_event');
    $sms_messages = $dispatch ? $this->dispatchEvent(SmsEvents::MESSAGE_PRE_PROCESS, [$sms_message])->getMessages() : [$sms_message];
    $sms_messages = $this->dispatchEvent(SmsEvents::MESSAGE_INCOMING_PRE_PROCESS, $sms_messages)->getMessages();

    if ($plugin instanceof SmsIncomingEventProcessorInterface) {
      $event = new SmsMessageEvent($sms_messages);
      $plugin->incomingEvent($event);
    }

    $this->dispatchEvent(SmsEvents::MESSAGE_INCOMING_POST_PROCESS, $sms_messages);
    $this->dispatchEvent(SmsEvents::MESSAGE_POST_PROCESS, $sms_messages);

    return $sms_messages;
  }

  /**
   * {@inheritdoc}
   */
  public function processDeliveryReport(Request $request, SmsGatewayInterface $sms_gateway) {
    $response = new Response();
    $reports = $sms_gateway->getPlugin()
      ->parseDeliveryReports($request, $response);

    $event = new SmsDeliveryReportEvent();
    $event
      ->setResponse($response)
      ->setReports($reports);
    $this->eventDispatcher
      ->dispatch($event, SmsEvents::DELIVERY_REPORT_POST_PROCESS);

    return $event->getResponse();
  }

  /**
   * Dispatch an SmsMessageEvent event for messages.
   *
   * @param string $event_name
   *   The event to trigger.
   * @param \Drupal\sms\Message\SmsMessageInterface[] $sms_messages
   *   The messages to dispatch.
   *
   * @return \Drupal\sms\Event\SmsMessageEvent
   *   The dispatched event.
   */
  protected function dispatchEvent($event_name, array $sms_messages) {
    $event = new SmsMessageEvent($sms_messages);
    return $this->eventDispatcher
      ->dispatch($event, $event_name);
  }

}
