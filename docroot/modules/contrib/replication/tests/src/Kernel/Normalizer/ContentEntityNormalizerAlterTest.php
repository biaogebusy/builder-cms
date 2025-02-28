<?php

namespace Drupal\Tests\replication\Kernel\Normalizer;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestMulRev;

/**
 * Tests the content serialization format.
 *
 * @group replication
 */
class ContentEntityNormalizerAlterTest extends NormalizerTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'serialization',
    'multiversion',
    'key_value',
    'system',
    'field',
    'entity_test',
    'replication',
    'text',
    'filter',
    'user',
    // This test module adds a subscriber to test with.
    'replication_alter_test',
  ];

  /**
   * @var string
   */
  protected $entityClass = EntityTest::class;

  protected function setUp() {
    parent::setUp();

    // Create a test entity to serialize.
    $this->values = [
      'name' => $this->randomMachineName(),
      'user_id' => 1,
      'field_test_text' => [
        'value' => $this->randomMachineName(),
        'format' => 'full_html',
      ],
    ];

    $this->entity = EntityTestMulRev::create($this->values);
    $this->entity->save();
  }

  /**
   * Tests the content entity normalize alter event.
   */
  public function testNormalizerAlterEvent() {
    $normalized = $this->serializer->normalize($this->entity);
    // The '_test' key should be added in
    // \Drupal\replication_alter_test\Event\ContentEntityTestAlterSubscriber::onAlterContentData
    $this->assertSame(['foo' => 'bar'], $normalized['_test']);
  }

}
