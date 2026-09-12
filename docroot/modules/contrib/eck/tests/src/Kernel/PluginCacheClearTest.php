<?php

namespace Drupal\Tests\eck\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eck\Entity\EckEntityType;

/**
 * Tests that plugin caches are cleared when an ECK entity type is created.
 *
 * @group eck
 */
class PluginCacheClearTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'eck', 'field'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('eck');
  }

  /**
   * Tests that local task derivatives appear after creating an ECK entity type.
   *
   * Plugin managers deriving from entity types (e.g. local tasks) must have
   * their cache cleared when a new ECK entity type is created.
   *
   * @see \Drupal\eck\EntityUpdateService::applyUpdates
   */
  public function testPluginCacheIsClearedAfterEntityTypeCreation(): void {
    $local_task_manager = $this->container->get('plugin.manager.menu.local_task');
    $definitions = $local_task_manager->getDefinitions();
    $this->assertArrayNotHasKey('eck.entity_content:test.eck_canonical_tab', $definitions);

    EckEntityType::create([
      'id' => 'test',
      'label' => 'Test',
    ])->save();

    $post_definitions = $local_task_manager->getDefinitions();
    $this->assertArrayHasKey('eck.entity_content:test.eck_canonical_tab', $post_definitions);
  }

}
