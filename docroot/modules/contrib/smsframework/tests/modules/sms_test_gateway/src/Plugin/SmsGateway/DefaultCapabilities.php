<?php

declare(strict_types = 1);

namespace Drupal\sms_test_gateway\Plugin\SmsGateway;

use Drupal\sms\Plugin\SmsGatewayPluginBase;
use Drupal\sms\Message\SmsMessageInterface;

/**
 * A gateway for testing undefined capability annotation values.
 *
 * This gateway does not provide any annotation values other than required
 * properties: 'id' and 'label'.
 *
 * @SmsGateway(
 *   id = "capabilities_default",
 *   label = @Translation("Default annotation capabilities")
 * )
 */
final class DefaultCapabilities extends SmsGatewayPluginBase {

  /**
   * {@inheritdoc}
   */
  public function send(SmsMessageInterface $sms) {
  }

}
