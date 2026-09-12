<?php

namespace Drupal\Tests\ctools\Kernel\Plugin\Block;

use Drupal\block\Entity\Block;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\ctools\Plugin\Block\EntityView;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\SchemaCheckTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the entity_view block plugin.
 *
 * @coversDefaultClass \Drupal\ctools\Plugin\Block\EntityView
 *
 * @group ctools
 */
class EntityViewTest extends KernelTestBase {

  use NodeCreationTrait;
  use UserCreationTrait;
  use SchemaCheckTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'ctools',
    'filter',
    'node',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['filter']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
  }

  /**
   * Tests plugin access.
   *
   * @covers ::access
   */
  public function testAccess() {
    // Create an unpublished node.
    $node = $this->createNode(['status' => 0]);

    $configuration = [
      'view_mode' => 'default',
    ];
    $definition = [
      'context_definitions' => [
        'entity' => new EntityContextDefinition('entity:node', NULL, TRUE, FALSE, NULL, $node),
      ],
      'provider' => 'ctools',
    ];
    $block = EntityView::create($this->container, $configuration, 'entity_view:node', $definition);
    $block->setContextValue('entity', $node);

    $access = $block->access(\Drupal::currentUser());
    $this->assertFalse($access);

    // Add a user than can see the unpublished block.
    $account = $this->createUser([], NULL, TRUE);
    $access = $block->access($account);
    $this->assertTrue($access);
  }

  /**
   * Tests the entity_view block config schema.
   */
  public function testBlockConfigSchema(): void {
    $id = strtolower($this->randomMachineName());
    $block = Block::create([
      'id' => $id,
      'theme' => 'stark',
      'weight' => 0,
      'status' => TRUE,
      'region' => 'content',
      'plugin' => 'entity_view:node',
      'settings' => [
        'label' => $this->randomMachineName(),
        'provider' => 'ctools',
        'label_display' => FALSE,
        'context_mapping' => ['entity' => '@node.node_route_context:node'],
        'view_mode' => 'default',
      ],
      'visibility' => [],
    ]);
    $block->save();
    $this->assertConfigSchemaByName("block.block.$id");
  }

}
