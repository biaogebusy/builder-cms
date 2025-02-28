<?php

namespace Drupal\Tests\entity\Kernel\QueryAccess;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Tests the generic query access handler.
 *
 * @coversDefaultClass \Drupal\entity\QueryAccess\EventOnlyQueryAccessHandler
 * @group entity
 */
class EventOnlyQueryAccessHandlerTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity',
    'entity_module_test',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');

    \Drupal::state()->set('test_event_only_query_access', TRUE);
  }

  /**
   * Tests cacheability with the event only query_access handler.
   *
   * If there is no additional cacheablility provided to the conditions, there
   * should be no render conrexts leaked.
   */
  public function testCacheableMetadataLeaks() {
    $renderer = $this->container->get('renderer');
    $render_context = new RenderContext();

    $node_type_storage = $this->entityTypeManager->getStorage('node_type');
    $node_type_storage->create(['type' => 'foo', 'name' => $this->randomString()])->save();

    $node_storage = $this->entityTypeManager->getStorage('node');
    $node_1 = $node_storage->create(['type' => 'foo', 'title' => $this->randomString()]);
    $node_1->save();
    $node_2 = $node_storage->create(['type' => 'bar', 'title' => $this->randomString()]);
    $node_2->save();

    $renderer->executeInRenderContext($render_context, static function () use ($node_storage) {
      $node_storage->getQuery()->accessCheck(TRUE)->execute();
    });
    $this->assertTrue($render_context->isEmpty(), 'Empty cacheability was not bubbled.');

    $cacheability = new CacheableMetadata();
    $cacheability->addCacheContexts(['user.permissions']);
    \Drupal::state()->set('event_only_query_acccess_cacheability', $cacheability);

    $render_context = new RenderContext();
    $renderer->executeInRenderContext($render_context, static function () use ($node_storage) {
      $node_storage->getQuery()->accessCheck(TRUE)->execute();
    });
    $this->assertFalse($render_context->isEmpty(), 'Cacheability was bubbled');
    $this->assertCount(1, $render_context);
    $this->assertEquals(['user.permissions'], $render_context[0]->getCacheContexts());
  }

  /**
   * Tests that entity types without a query access handler still fire events.
   */
  public function testEventOnlyQueryAccessHandlerEventSubscriber() {
    $node_type_storage = $this->entityTypeManager->getStorage('node_type');
    $node_type_storage->create(['type' => 'foo', 'name' => $this->randomString()])->save();
    $node_type_storage->create(['type' => 'bar', 'name' => $this->randomString()])->save();

    $node_storage = $this->entityTypeManager->getStorage('node');
    $node_1 = $node_storage->create(['type' => 'foo', 'title' => $this->randomString()]);
    $node_1->save();
    $node_2 = $node_storage->create(['type' => 'bar', 'title' => $this->randomString()]);
    $node_2->save();

    $unfiltered = $node_storage->getQuery()->accessCheck(FALSE)->execute();
    $this->assertCount(2, $unfiltered, 'Both nodes show up when access checking is turned off.');
    $this->assertArrayHasKey($node_1->id(), $unfiltered, 'foo nodes were not filtered out.');
    $this->assertArrayHasKey($node_2->id(), $unfiltered, 'bar nodes were not filtered out.');

    $filtered = $node_storage->getQuery()->accessCheck(TRUE)->execute();
    $this->assertCount(1, $filtered, 'Only one node shows up when access checking is turned on.');
    $this->assertArrayHasKey($node_1->id(), $filtered, 'foo nodes were not filtered out.');
    $this->assertArrayNotHasKey($node_2->id(), $filtered, 'bar nodes were filtered out.');
  }

}
