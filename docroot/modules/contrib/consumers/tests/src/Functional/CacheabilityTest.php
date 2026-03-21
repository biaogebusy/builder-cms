<?php

namespace Drupal\Tests\consumers\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\consumers\Entity\Consumer;
use Drupal\consumers\MissingConsumer;
use Drupal\Tests\jsonapi\Functional\JsonApiFunctionalTestBase;

/**
 * Tests the cacheability of the consumer client id field.
 *
 * @group consumers
 */
class CacheabilityTest extends JsonApiFunctionalTestBase {

  /**
   * The required modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'consumers',
    'consumers_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The content type for testing.
   *
   * @var \Drupal\node\Entity\NodeType
   */
  protected $contentType;

  /**
   * Test node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * Test consumer.
   *
   * @var \Drupal\consumers\Entity\Consumer
   */
  protected $consumer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->contentType = $this->drupalCreateContentType([
      'type' => CONSUMERS_TEST_NODE_TYPE,
    ]);
    $this->node = $this->createNode([
      'type' => CONSUMERS_TEST_NODE_TYPE,
    ]);
    $this->consumer = Consumer::create([
      'client_id' => $this->randomMachineName(),
      'label' => $this->randomString(),
    ]);
    $this->consumer->save();
    drupal_flush_all_caches();
  }

  /**
   * Check client id cacheability depending on provided consumer.
   */
  public function testClientIdCacheability() {
    $path = sprintf(
      '/jsonapi/node/%s/%s',
      $this->contentType->id(),
      $this->node->uuid()
    );
    $query = [
      'consumerId' => $this->consumer->getClientId(),
    ];

    // Pass test consumer and make sure its client id is returned.
    $output = Json::decode($this->drupalGet($path, ['query' => $query]));
    $this->assertArrayHasKey('consumer_client_id', $output['data']['attributes']);
    $this->assertEquals($this->consumer->getClientId(), $output['data']['attributes']['consumer_client_id']);

    // Check if test consumer result was not cached and default returned
    // when no consumer passed.
    $output = Json::decode($this->drupalGet($path));
    $this->assertArrayHasKey('consumer_client_id', $output['data']['attributes']);
    $this->assertEquals('default_consumer', $output['data']['attributes']['consumer_client_id']);
  }

  /**
   * Verify the site still loads if there are no consumer entities.
   *
   * @covers \Drupal\consumers\EventSubscriber\ConsumerVaryEventSubscriber::onRespond
   */
  public function testNoConsumerEntities() {
    // Load, and then delete, all consumer entities.
    $consumers = Consumer::loadMultiple();
    foreach ($consumers as $consumer) {
      $consumer->delete();
    }
    $this->assertEmpty(Consumer::loadMultiple());
    // Assert there is no exception.
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Verify the site still loads if there are no default consumer entities.
   *
   * @covers \Drupal\consumers\EventSubscriber\ConsumerVaryEventSubscriber::onRespond
   */
  public function testNoDefaultConsumer() {
    // Load, and then delete, all consumer entities.
    $consumers = Consumer::loadMultiple();
    foreach ($consumers as $consumer) {
      $consumer->delete();
    }
    $this->assertEmpty(Consumer::loadMultiple());

    // Create a single consumer, but make it no the default.
    $consumer = Consumer::create([
      'client_id' => $this->randomMachineName(),
      'label' => $this->randomString(),
      'is_default' => FALSE,
    ]);
    $consumer->save();

    // Verify there is no default consumer by trying to negotiate one from the
    // request, which has no consumer_id in the headers so tries to load the
    // default and then throws an exception.
    /** @var \Drupal\consumers\Negotiator $negotiator  */
    $negotiator = $this->container->get('consumer.negotiator');
    $this->expectException(MissingConsumer::class);
    $this->expectExceptionMessage('Unable to find the default consumer.');
    $negotiator->negotiateFromRequest();

    // Verify the above exception is handled properly in
    // \Drupal\consumers\EventSubscriber\ConsumerVaryEventSubscriber::onRespond
    // when trying to load any page.
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);
  }

}
