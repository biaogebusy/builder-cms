<?php

namespace Drupal\Tests\commerce_payment\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;

/**
 * @coversDefaultClass \Drupal\commerce_payment\Exception\PaymentGatewayException
 * @group commerce
 */
class PaymentGatewayExceptionTest extends UnitTestCase {

  /**
   * @covers ::createForPayment
   */
  public function testCreateForPayment() {
    $payment = $this->createMock(PaymentInterface::class);
    $payment_method = $this->createMock(PaymentMethodInterface::class);
    $payment->method('getPaymentMethod')->willReturn($payment_method);
    $previous = new \RuntimeException();
    $exception = PaymentGatewayException::createForPayment($payment, 'Test message', 10, $previous);
    $this->assertSame($payment, $exception->getPayment());
    $this->assertSame($payment_method, $exception->getPaymentMethod());
    $this->assertSame('Test message', $exception->getMessage());
    $this->assertSame(10, $exception->getCode());
    $this->assertSame($previous, $exception->getPrevious());
  }

  /**
   * @covers ::createForPayment
   */
  public function testCreateForPaymentWithMethod() {
    $payment_method = $this->createMock(PaymentMethodInterface::class);
    $exception = PaymentGatewayException::createForPayment($payment_method, 'Another test message');
    $this->assertNull($exception->getPayment());
    $this->assertSame($payment_method, $exception->getPaymentMethod());
    $this->assertSame('Another test message', $exception->getMessage());
    $this->assertSame(0, $exception->getCode());
    $this->assertNull($exception->getPrevious());
  }

}
