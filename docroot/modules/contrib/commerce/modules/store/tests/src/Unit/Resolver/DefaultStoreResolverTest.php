<?php

namespace Drupal\Tests\commerce_store\Unit\Resolver;

use Drupal\Tests\UnitTestCase;
use Drupal\commerce_store\Resolver\DefaultStoreResolver;

/**
 * @coversDefaultClass \Drupal\commerce_store\Resolver\DefaultStoreResolver
 * @group commerce_store
 */
class DefaultStoreResolverTest extends UnitTestCase {

  /**
   * The resolver.
   *
   * @var \Drupal\commerce_store\Resolver\DefaultStoreResolver
   */
  protected $resolver;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $storage = $this->getMockBuilder('Drupal\commerce_store\StoreStorage')
      ->disableOriginalConstructor()
      ->getMock();
    $storage->expects($this->once())
      ->method('loadDefault')
      ->willReturn('testStore');

    $entity_type_manager = $this->getMockBuilder('\Drupal\Core\Entity\EntityTypeManager')
      ->disableOriginalConstructor()
      ->getMock();
    $entity_type_manager->expects($this->once())
      ->method('getStorage')
      ->with('commerce_store')
      ->willReturn($storage);

    $this->resolver = new DefaultStoreResolver($entity_type_manager);
  }

  /**
   * @covers ::resolve
   */
  public function testResolve() {
    $this->assertEquals('testStore', $this->resolver->resolve());
  }

}
