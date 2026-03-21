<?php

namespace Drupal\Tests\consumers\Kernel;

use Drupal\consumers\Entity\Consumer;
use Drupal\consumers\Entity\ConsumerInterface;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * The negotiator test.
 *
 * @group consumers
 */
class NegotiatorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'consumers',
    'user',
    'file',
    'image',
    'system',
  ];

  /**
   * The consumer.
   *
   * @var \Drupal\consumers\Entity\ConsumerInterface
   */
  protected $consumer;

  /**
   * The default consumer.
   *
   * @var \Drupal\consumers\Entity\ConsumerInterface
   */
  protected $defaultConsumer;

  /**
   * The negotiator service.
   *
   * @var \Drupal\consumers\Negotiator
   */
  protected $negotiator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('consumer');
    $this->installEntitySchema('file');
    $this->installConfig(['user']);
    $this->negotiator = $this->container->get('consumer.negotiator');

    $this->consumer = Consumer::create([
      'label' => 'test',
      'client_id' => 'test_consumer_id',
    ]);
    $this->consumer->save();

    $this->defaultConsumer = Consumer::create([
      'label' => 'default',
      'client_id' => 'default',
      'is_default' => TRUE,
    ]);
    $this->defaultConsumer->save();
  }

  /**
   * Test negotiation from request with header.
   */
  public function testNegotiateFromRequestWithHeader(): void {
    $request = Request::create('/');
    $request->headers->set('X-Consumer-ID', $this->consumer->getClientId());
    $consumer = $this->negotiator->negotiateFromRequest($request);

    $this->assertInstanceOf(ConsumerInterface::class, $consumer);
    $this->assertEquals($this->consumer->getClientId(), $consumer->getClientId());
    $this->assertEquals($this->consumer->getClientId(), $request->attributes->get('consumer_id'));

    // If consumer doesn't exist, expected is to fallback on default consumer.
    $request->headers->set('X-Consumer-ID', 'unknown');
    $consumer = $this->negotiator->negotiateFromRequest($request);

    $this->assertInstanceOf(ConsumerInterface::class, $consumer);
    $this->assertEquals($this->defaultConsumer->getClientId(), $consumer->getClientId());
    $this->assertEquals($this->defaultConsumer->getClientId(), $request->attributes->get('consumer_id'));
  }

  /**
   * Test client ID negotiation from request with header.
   */
  public function testNegotiateClientIdFromRequestWithHeader(): void {
    $request = Request::create('/');
    $request->headers->set('X-Consumer-ID', $this->consumer->getClientId());
    $client_id = $this->negotiator->negotiateClientIdFromRequest($request);

    $this->assertEquals($this->consumer->getClientId(), $client_id);
    $this->assertEquals($this->consumer->getClientId(), $request->attributes->get('consumer_id'));

    // If consumer doesn't exist, expected is to fallback on default consumer.
    $request->headers->set('X-Consumer-ID', 'unknown');
    $client_id = $this->negotiator->negotiateClientIdFromRequest($request);

    $this->assertEquals($this->defaultConsumer->getClientId(), $client_id);
    $this->assertEquals($this->defaultConsumer->getClientId(), $request->attributes->get('consumer_id'));
  }

  /**
   * Test negotiation from request with query string parameter.
   */
  public function testNegotiateFromRequestWithQuery(): void {
    $request = Request::create('/', 'GET', [
      'consumerId' => $this->consumer->getClientId(),
    ]);
    $consumer = $this->negotiator->negotiateFromRequest($request);

    $this->assertInstanceOf(ConsumerInterface::class, $consumer);
    $this->assertEquals($this->consumer->getClientId(), $consumer->getClientId());
    $this->assertEquals($this->consumer->getClientId(), $request->attributes->get('consumer_id'));

    // If consumer doesn't exist, expected is to fallback on default consumer.
    $request = Request::create('/', 'GET', [
      'consumerId' => 'unknown',
    ]);
    $consumer = $this->negotiator->negotiateFromRequest($request);

    $this->assertInstanceOf(ConsumerInterface::class, $consumer);
    $this->assertEquals($this->defaultConsumer->getClientId(), $consumer->getClientId());
    $this->assertEquals($this->defaultConsumer->getClientId(), $request->attributes->get('consumer_id'));
  }

  /**
   * Test client ID  negotiation from request with query string parameter.
   */
  public function testNegotiateClientIdFromRequestWithQuery(): void {
    $request = Request::create('/', 'GET', [
      'consumerId' => $this->consumer->getClientId(),
    ]);
    $client_id = $this->negotiator->negotiateClientIdFromRequest($request);

    $this->assertEquals($this->consumer->getClientId(), $client_id);
    $this->assertEquals($this->consumer->getClientId(), $request->attributes->get('consumer_id'));

    // If consumer doesn't exist, expected is to fallback on default consumer.
    $request = Request::create('/', 'GET', [
      'consumerId' => 'unknown',
    ]);
    $client_id = $this->negotiator->negotiateClientIdFromRequest($request);

    $this->assertEquals($this->defaultConsumer->getClientId(), $client_id);
    $this->assertEquals($this->defaultConsumer->getClientId(), $request->attributes->get('consumer_id'));
  }

}
