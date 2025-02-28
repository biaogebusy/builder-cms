<?php

namespace Drupal\Tests\xls_serialization\Unit\EventSubscriber;

use Drupal\Tests\UnitTestCase;
use Drupal\xls_serialization\EventSubscriber\Subscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Tests the XLS serialization event subscriber.
 *
 * @group xls_serialization
 *
 * @coversDefaultClass \Drupal\xls_serialization\EventSubscriber\Subscriber
 */
class SubscriberTest extends UnitTestCase {

  /**
   * @covers ::onKernelRequest
   */
  public function testOnKernelRequest() {
    // Both xls and xlsx should be set.
    $request = $this->prophesize(Request::class);
    $request->setFormat('xls', ['application/vnd.ms-excel'])->shouldBeCalled();
    $request->setFormat('xlsx', ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->shouldBeCalled();
    $event = $this->prophesize(RequestEvent::class);
    $event->getRequest()->willReturn($request->reveal());
    $subscriber = new Subscriber();
    $subscriber->onKernelRequest($event->reveal());
  }

}
