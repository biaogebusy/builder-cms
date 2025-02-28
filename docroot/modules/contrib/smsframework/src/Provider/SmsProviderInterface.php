<?php

declare(strict_types = 1);

namespace Drupal\sms\Provider;

use Drupal\sms\Entity\SmsGatewayInterface;
use Drupal\sms\Message\SmsMessageInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides an interface for sending messages.
 */
interface SmsProviderInterface {

  /**
   * Queue a SMS message for sending or receiving.
   *
   * @param \Drupal\sms\Message\SmsMessageInterface $sms_message
   *   A SMS message.
   *
   * @return \Drupal\sms\Entity\SmsMessageInterface[]
   *   The queued messages. A single message may be transformed into many.
   *
   * @throws \Drupal\sms\Exception\SmsDirectionException
   *   Thrown if no direction is set for the message.
   * @throws \Drupal\sms\Exception\RecipientRouteException
   *   Thrown if no gateway could be determined for the message.
   */
  public function queue(SmsMessageInterface $sms_message);

  /**
   * Sends an SMS using the active gateway.
   *
   * It is preferred to use queue method over directly invoking send().
   *
   * @param \Drupal\sms\Message\SmsMessageInterface $sms
   *   The message to be sent.
   *
   * @return \Drupal\sms\Message\SmsMessageInterface[]
   *   The messages sent in this sending operation. The message sent can be
   *   transformed into multiple messages depending on gateway and event
   *   subscribers. Therefore this function can return multiple messages.
   *
   * @throws \Drupal\sms\Exception\RecipientRouteException
   *   Thrown if no gateway could be determined for the message.
   */
  public function send(SmsMessageInterface $sms);

  /**
   * Handles a message sent from the gateway to the site.
   *
   * @param \Drupal\sms\Message\SmsMessageInterface $sms_message
   *   The incoming message to process.
   *
   * @return \Drupal\sms\Message\SmsMessageInterface[]
   *   The messages received in an incoming operation.
   */
  public function incoming(SmsMessageInterface $sms_message);

  /**
   * Handles delivery reports pushed to the site.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request that contains the delivery report.
   * @param \Drupal\sms\Entity\SmsGatewayInterface $gateway
   *   The gateway designated to process the delivery report.
   */
  public function processDeliveryReport(Request $request, SmsGatewayInterface $gateway);

}
