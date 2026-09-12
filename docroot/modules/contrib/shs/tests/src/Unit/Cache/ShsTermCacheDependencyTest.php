<?php

namespace Drupal\Tests\shs\Unit\Cache;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\shs\Cache\ShsTermCacheDependency;
use PHPUnit\Framework\TestCase;

/**
 * Tests the cacheable dependency for taxonomy terms.
 *
 * @coversDefaultClass \Drupal\shs\Cache\ShsTermCacheDependency
 *
 * @group shs
 */
class ShsTermCacheDependencyTest extends TestCase {

  /**
   * The taxonomy term entity type.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityType = $this->createMock(EntityTypeInterface::class);
    $this->entityType->method('getListCacheTags')
      ->willReturn(['taxonomy_term_list']);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects($this->any())
      ->method('getDefinition')
      ->with('taxonomy_term')
      ->willReturn($this->entityType);

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entity_type_manager);
    \Drupal::setContainer($container);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
  }

  /**
   * Tests that cache tags include the vocabulary-specific list cache tag.
   *
   * @covers ::__construct
   * @covers ::getCacheTags
   */
  public function testBundleListCacheTag(): void {
    $this->entityType->expects($this->once())
      ->method('getBundleListCacheTags')
      ->with('tags')
      ->willReturn(['taxonomy_term_list:tags']);

    $dependency = new ShsTermCacheDependency(['taxonomy_term:1'], 'tags');

    $this->assertSame([
      'taxonomy_term_values',
      'taxonomy_term:1',
      'taxonomy_term_list',
      'taxonomy_term_list:tags',
    ], $dependency->getCacheTags());
  }

  /**
   * Tests that the bundle-specific tag is omitted without a vocabulary.
   *
   * @covers ::__construct
   * @covers ::getCacheTags
   */
  public function testCacheTagsWithoutBundle(): void {
    $this->entityType->expects($this->never())
      ->method('getBundleListCacheTags');

    $dependency = new ShsTermCacheDependency(['taxonomy_term:1']);

    $this->assertSame([
      'taxonomy_term_values',
      'taxonomy_term:1',
      'taxonomy_term_list',
    ], $dependency->getCacheTags());
  }

}
