<?php

declare(strict_types = 1);

namespace Drupal\sms_test_gateway\Plugin\SmsGateway;

use Drupal\sms\Plugin\SmsGatewayPluginBase;
use Drupal\sms\Message\SmsMessageInterface;

/**
 * Defines a gateway which does not implement incoming messages.
 *
 * @SmsGateway(
 *   id = "memory_noincoming",
 *   label = @Translation("No Incoming"),
 * )
 */
final class NoIncoming extends SmsGatewayPluginBase {

  /**
   * {@inheritdoc}
   */
  public function send(SmsMessageInterface $sms) {
  }

}
