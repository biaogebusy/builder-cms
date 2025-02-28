<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms_user\Kernel;

use Drupal\Tests\sms\Kernel\SmsFrameworkKernelBase;
use Drupal\sms\Entity\SmsMessage;
use Drupal\sms\Entity\PhoneNumberSettings;
use Drupal\sms\Direction;

/**
 * General tests for SMS User.
 *
 * @group SMS Framework
 */
class SmsFrameworkUserTest extends SmsFrameworkKernelBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'sms',
    'sms_user',
    'sms_test_gateway',
    'user',
    'telephone',
    'dynamic_entity_reference',
    'field',
  ];

  /**
   * The SMS provider.
   *
   * @var \Drupal\sms\Provider\SmsProviderInterface
   */
  protected $smsProvider;

  /**
   * A memory gateway.
   *
   * @var \Drupal\sms\Entity\SmsGatewayInterface
   */
  protected $gateway;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('system', ['sequences']);
    $this->installConfig('sms_user');
    $this->installEntitySchema('user');
    $this->installEntitySchema('sms');
    $this->installEntitySchema('sms_phone_number_verification');
    $this->smsProvider = $this->container->get('sms.provider');
    $this->gateway = $this->createMemoryGateway(['skip_queue' => TRUE]);
    $this->setFallbackGateway($this->gateway);
  }

  /**
   * Ensure account registration service does not crash if missing user config.
   *
   * Ensure sms_user.account_registration service does not crash and burn if
   * there are no phone number settings for user.user.
   */
  public function testAccountRegistrationNoPhoneSettings(): void {
    $this->config('sms_user.settings')
      ->set('account_registration.unrecognized_sender.status', 1)
      ->set('account_registration.unrecognized_sender.reply.status', 1)
      ->save();

    $message = $this->randomString();
    $incoming = SmsMessage::create()
      ->setSenderNumber('+123')
      ->setDirection(Direction::INCOMING)
      ->setMessage($message)
      ->addRecipients($this->randomPhoneNumbers(1))
      ->setGateway($this->gateway);
    $incoming->setResult($this->createMessageResult($incoming));
    $this->smsProvider->queue($incoming);

    $this->assertEquals($message, sms_test_gateway_get_incoming()['message']);
    // Make sure the phone number settings does not exist, in case it makes its
    // way into this test in the future.
    $this->assertNull(PhoneNumberSettings::load('user.user'), 'No phone numbser settings for user.user.');
  }

}
