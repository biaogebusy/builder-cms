<?php

declare(strict_types = 1);

namespace Drupal\sms\Event;

/**
 * Defines SMS Framework events.
 */
final class SmsEvents {

  /**
   * Determines valid gateways for a recipient phone number.
   *
   * This event is not always dispatched. It is only dispatch if no other
   * preprocessors have added a gateway to a message.
   *
   * If you don't know whether you should add a gateway for a recipient, then
   * it is best to not do anything at all. Let the rest of the framework
   * continue to try to find a gateway.
   *
   * Only one gateway will be applied to the message for the recipient. The
   * gateway with the largest priority wins.
   *
   * @Event
   *
   * @see \Drupal\sms\Event\RecipientGatewayEvent
   */
  const MESSAGE_GATEWAY = 'sms.message.gateway';

  /**
   * Process a SMS message before it is queued, sent, or received.
   *
   * @Event
   *
   * @see \Drupal\sms\Event\SmsMessageEvent
   */
  const MESSAGE_PRE_PROCESS = 'sms.message.pre_process';

  /**
   * Process an SMS message after it is sent or received.
   *
   * Unlike its counterpart, MESSAGE_PRE_PROCESS, this event is not triggered
   * for queued messages because there is associated result.
   *
   * @Event
   *
   * @see \Drupal\sms\Event\SmsMessageEvent
   */
  const MESSAGE_POST_PROCESS = 'sms.message.post_process';

  /**
   * Process a SMS message after it is queued.
   *
   * @Event
   *
   * @see \Drupal\sms\Event\SmsMessageEvent
   */
  const MESSAGE_QUEUE_PRE_PROCESS = 'sms.message.queue.pre_process';

  /**
   * Process a SMS message after it is queued.
   *
   * @Event
   *
   * @see \Drupal\sms\Event\SmsMessageEvent
   */
  const MESSAGE_QUEUE_POST_PROCESS = 'sms.message.queue.post_process';

  /**
   * Process a SMS message before it is sent.
   *
   * @Event
   *
   * @see \Drupal\sms\Event\SmsMessageEvent
   */
  const MESSAGE_OUTGOING_PRE_PROCESS = 'sms.message.outgoing.pre_process';

  /**
   * Process a SMS message after it is sent.
   *
   * @Event
   *
   * @see \Drupal\sms\Event\SmsMessageEvent
   */
  const MESSAGE_OUTGOING_POST_PROCESS = 'sms.message.outgoing.post_process';

  /**
   * Process a SMS message before it is received.
   *
   * @Event
   *
   * @see \Drupal\sms\Event\SmsMessageEvent
   */
  const MESSAGE_INCOMING_PRE_PROCESS = 'sms.message.incoming.pre_process';

  /**
   * Process a SMS message after it is received.
   *
   * @Event
   *
   * @see \Drupal\sms\Event\SmsMessageEvent
   */
  const MESSAGE_INCOMING_POST_PROCESS = 'sms.message.incoming.post_process';

  /**
   * Process delivery reports after they are created by the gateway plugin.
   *
   * This event grants an opportunity to modify the HTTP response if the
   * delivery reports were pushed to the site.
   *
   * @Event
   *
   * @see \Drupal\sms\Event\SmsDeliveryReportEvent
   */
  const DELIVERY_REPORT_POST_PROCESS = 'sms.report.post_process';

  /**
   * Resolve phone numbers for an entity.
   *
   * @Event
   *
   * @see \Drupal\sms\Event\SmsEntityPhoneNumber
   */
  const ENTITY_PHONE_NUMBERS = 'sms.entity_phone_numbers';

}
