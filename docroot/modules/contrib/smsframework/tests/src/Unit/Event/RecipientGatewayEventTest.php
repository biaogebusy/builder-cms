<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms\Unit\Event;

use Drupal\Tests\UnitTestCase;
use Drupal\Tests\sms\Functional\SmsFrameworkTestTrait;
use Drupal\sms\Event\RecipientGatewayEvent;
use Drupal\sms\Entity\SmsGatewayInterface;

/**
 * Unit Tests for SmsMessage.
 *
 * @group SMS Framework
 * @coversDefaultClass \Drupal\sms\Event\RecipientGatewayEvent
 */
final class RecipientGatewayEventTest extends UnitTestCase {

  use SmsFrameworkTestTrait;

  /**
   * Tests gateway sort.
   *
   * @covers ::getGatewaysSorted
   */
  public function testSortFunction(): void {
    $number = $this->randomPhoneNumbers()[0];
    $event = $this->createEvent($number);

    $gateway = $this->createMock(SmsGatewayInterface::class);
    $gateway
      ->expects($this->any())
      ->method('id')
      ->willReturn('gateway_first');
    $event->addGateway($gateway, 100);

    $gateway = $this->createMock(SmsGatewayInterface::class);
    $gateway
      ->expects($this->any())
      ->method('id')
      ->willReturn('gateway_second');
    $event->addGateway($gateway, 200);

    $sorted = $event->getGatewaysSorted();
    static::assertCount(2, $sorted);
    static::assertEquals('gateway_second', $sorted[0]->id());
    static::assertEquals('gateway_first', $sorted[1]->id());
  }

  /**
   * Tests recipient constructor.
   *
   * @covers ::getRecipient
   */
  public function testRecipientConstructor(): void {
    $number = $this->randomPhoneNumbers()[0];
    $event = $this->createEvent($number);
    static::assertEquals($number, $event->getRecipient(), 'Constructor recipient is set');
  }

  /**
   * Tests recipient methods.
   *
   * @covers ::getRecipient
   * @covers ::setRecipient
   */
  public function testRecipient(): void {
    $event = $this->createEvent($this->randomPhoneNumbers()[0]);

    $number = $this->randomPhoneNumbers()[0];
    $event->setRecipient($number);
    static::assertEquals($number, $event->getRecipient(), 'Recipient is set via setRecipient');
  }

  /**
   * Tests gateway add and getter.
   *
   * @covers ::addGateway
   * @covers ::getGateways
   */
  public function testGetGateways(): void {
    $event = $this->createEvent($this->randomPhoneNumbers()[0]);

    $gateway = $this->createMock(SmsGatewayInterface::class);
    $gateway
      ->expects($this->any())
      ->method('id')
      ->willReturn('gateway_1');
    $event->addGateway($gateway, 200);

    $gateway = $this->createMock(SmsGatewayInterface::class);
    $gateway
      ->expects($this->any())
      ->method('id')
      ->willReturn('gateway_2');
    $event->addGateway($gateway, 400);

    $gateways = $event->getGateways();
    static::assertEquals('gateway_1', $gateways[0][0]->id());
    static::assertEquals(200, $gateways[0][1]);
    static::assertEquals('gateway_2', $gateways[1][0]->id());
    static::assertEquals(400, $gateways[1][1]);
  }

  /**
   * Tests removing gateway with both ID and priority.
   *
   * @covers ::removeGateway
   */
  public function testGatewayRemove(): void {
    $event = $this->createEvent($this->randomPhoneNumbers()[0]);

    $gateway = $this->createMock(SmsGatewayInterface::class);
    $gateway
      ->expects($this->any())
      ->method('id')
      ->willReturn('gateway_1');
    $event->addGateway($gateway, 200);

    $gateway = $this->createMock(SmsGatewayInterface::class);
    $gateway
      ->expects($this->any())
      ->method('id')
      ->willReturn('gateway_1');
    $event->addGateway($gateway, 400);

    $gateway = $this->createMock(SmsGatewayInterface::class);
    $gateway
      ->expects($this->any())
      ->method('id')
      ->willReturn('gateway_2');
    $event->addGateway($gateway, 600);

    static::assertCount(3, $event->getGateways(), 'There are three gateways.');

    $event->removeGateway('gateway_1', 400);
    static::assertCount(2, $event->getGateways(), 'One gateways was removed.');
  }

  /**
   * Tests removing gateways with same identifier.
   *
   * @covers ::removeGateway
   */
  public function testGatewayRemoveAllSameId(): void {
    $event = $this->createEvent($this->randomPhoneNumbers()[0]);

    $gateway = $this->createMock(SmsGatewayInterface::class);
    $gateway
      ->expects($this->any())
      ->method('id')
      ->willReturn('gateway_1');
    $event->addGateway($gateway, 200);

    $gateway = $this->createMock(SmsGatewayInterface::class);
    $gateway
      ->expects($this->any())
      ->method('id')
      ->willReturn('gateway_1');
    $event->addGateway($gateway, 400);

    $gateway = $this->createMock(SmsGatewayInterface::class);
    $gateway
      ->expects($this->any())
      ->method('id')
      ->willReturn('gateway_2');
    $event->addGateway($gateway, 600);

    static::assertCount(3, $event->getGateways(), 'There are three gateways.');

    $event->removeGateway('gateway_1');
    static::assertCount(1, $event->getGateways(), 'Two gateways were removed.');
  }

  /**
   * Create a new event for testing.
   *
   * @param string $recipient
   *   The recipient phone number.
   *
   * @return \Drupal\sms\Event\RecipientGatewayEvent
   *   A new recipient gateway event instance.
   */
  public function createEvent($recipient): RecipientGatewayEvent {
    return new RecipientGatewayEvent($recipient);
  }

}
