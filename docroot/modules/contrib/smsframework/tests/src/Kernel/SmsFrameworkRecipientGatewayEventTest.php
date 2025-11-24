<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms\Kernel;

use Drupal\sms\Entity\SmsMessage;
use Drupal\sms\Direction;
use Drupal\sms\Provider\SmsProviderInterface;

/**
 * Tests SMS Framework provider service.
 *
 * @group SMS Framework
 */
final class SmsFrameworkRecipientGatewayEventTest extends SmsFrameworkKernelBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'sms', 'sms_test', 'sms_test_gateway', 'field', 'telephone',
    'dynamic_entity_reference',
  ];

  /**
   * The default SMS provider.
   *
   * @var \Drupal\sms\Provider\SmsProviderInterface
   */
  private SmsProviderInterface $smsProvider;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('sms');
    $this->smsProvider = $this->container->get('sms.provider');
    $this->setFallbackGateway(NULL);
  }

  /**
   * Test gateways are applied from the test event subscriber.
   *
   * @see \Drupal\sms_test\EventSubscriber\SmsTestEventSubscriber
   */
  public function testGatewayEventSubscriber(): void {
    $gateway_200 = $this->createMemoryGateway(['id' => 'test_gateway_200']);
    $gateway_200
      ->setSkipQueue(TRUE)
      ->save();
    $gateway_400 = $this->createMemoryGateway(['id' => 'test_gateway_400']);
    $gateway_400
      ->setSkipQueue(TRUE)
      ->save();

    \Drupal::state()->set('sms_test_event_subscriber__test_gateway_200', TRUE);
    \Drupal::state()->set('sms_test_event_subscriber__test_gateway_400', TRUE);

    $sms_message = SmsMessage::create()
      ->setDirection(Direction::OUTGOING)
      ->setMessage($this->randomString())
      ->addRecipients($this->randomPhoneNumbers());

    $sms_messages = $this->smsProvider->queue($sms_message);
    static::assertCount(1, $sms_messages, 'One message dispatched.');
    static::assertEquals('test_gateway_400', $sms_messages[0]->getGateway()->id());

    static::assertCount(0, $this->getTestMessages($gateway_200), 'Message not sent through gateway_200');
    static::assertCount(1, $this->getTestMessages($gateway_400), 'Message sent through gateway_400');
  }

}
