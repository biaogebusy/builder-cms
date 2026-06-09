<?php

namespace Drupal\Tests\ai_automators\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\ai_automators\AiAutomatorInterface;
use Drupal\node\Entity\NodeType;

/**
 * Tests the action worker type and its action derivative.
 *
 * @group ai_automators
 */
class ActionProcessingTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'node',
    'text',
    'token',
    'filter',
    'options',
    'key',
    'ai',
    'ai_test',
    'ai_automators',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('node');
    $this->installEntitySchema('ai_mock_provider_result');
    $this->installConfig([
      'system',
      'field',
      'file',
      'node',
      'filter',
    ]);

    // Create article content type with a text field.
    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();

    NodeType::create([
      'type' => 'page',
      'name' => 'Page',
    ])->save();

    if (!FieldStorageConfig::loadByName('node', 'body')) {
      FieldStorageConfig::create([
        'field_name' => 'body',
        'entity_type' => 'node',
        'type' => 'text_long',
      ])->save();
    }
    FieldConfig::create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Body',
    ])->save();
  }

  /**
   * Creates an AI automator config entity with action worker type.
   *
   * @param array $overrides
   *   Optional overrides for automator properties.
   *
   * @return \Drupal\ai_automators\AiAutomatorInterface
   *   The created automator config entity.
   */
  protected function createActionAutomator(array $overrides = []): AiAutomatorInterface {
    $pluginOverrides = [];
    if (isset($overrides['edit_mode'])) {
      $pluginOverrides['automator_edit_mode'] = $overrides['edit_mode'] ? 1 : 0;
    }

    $values = $overrides + [
      'id' => 'node.article.body.action',
      'label' => 'Action Body Generator',
      'entity_type' => 'node',
      'bundle' => 'article',
      'field_name' => 'body',
      'worker_type' => 'action',
      'rule' => 'llm_text_long',
      'input_mode' => 'base',
      'weight' => 100,
      'edit_mode' => FALSE,
      'base_field' => 'title',
      'prompt' => '{{ context }}',
      'token' => '',
    ];
    $values['plugin_config'] = $pluginOverrides + [
      'automator_enabled' => 1,
      'automator_rule' => 'llm_text_long',
      'automator_mode' => 'base',
      'automator_base_field' => 'title',
      'automator_prompt' => '{{ context }}',
      'automator_token' => '',
      'automator_edit_mode' => $values['edit_mode'] ? 1 : 0,
      'automator_label' => 'Action Body Generator',
      'automator_weight' => '100',
      'automator_worker_type' => 'action',
      'automator_ai_provider' => 'echoai',
      'automator_ai_model' => 'default',
    ];

    /** @var \Drupal\ai_automators\AiAutomatorInterface $automator */
    $automator = $this->container->get('entity_type.manager')
      ->getStorage('ai_automator')
      ->create($values);
    $automator->save();

    return $automator;
  }

  /**
   * Tests derivative discovery and automatic cache invalidation on CRUD.
   */
  public function testActionDerivativeDiscoveryAndCaching(): void {
    $actionManager = $this->container->get('plugin.manager.action');

    // Before creating an automator, no derivative should exist.
    $definitions = $actionManager->getDefinitions();
    $this->assertArrayNotHasKey('ai_automators_run:node.article.body.action', $definitions);

    // Create and save. Derivative should appear without manual cache clear.
    $automator = $this->createActionAutomator();

    $definitions = $actionManager->getDefinitions();
    $this->assertArrayHasKey('ai_automators_run:node.article.body.action', $definitions);
    $this->assertEquals('node', $definitions['ai_automators_run:node.article.body.action']['type']);

    // Delete the automator. Derivative should disappear without manual clear.
    $automator->delete();

    $definitions = $actionManager->getDefinitions();
    $this->assertArrayNotHasKey('ai_automators_run:node.article.body.action', $definitions);
  }

  /**
   * Tests action execution, presave skip, and edit_mode behavior.
   */
  public function testActionExecution(): void {
    $this->createActionAutomator();

    // Action worker should NOT process during entity presave.
    $node = Node::create([
      'type' => 'article',
      'title' => 'Test Article',
    ]);
    $node->save();
    $this->assertTrue($node->get('body')->isEmpty(), 'Action worker should not run during presave.');

    // Explicit action execution should populate the field.
    /** @var \Drupal\ai_automators\Plugin\Action\RunAutomatorAction $action */
    $action = $this->container->get('plugin.manager.action')
      ->createInstance('ai_automators_run:node.article.body.action');
    $action->execute($node);
    $this->assertFalse($node->get('body')->isEmpty(), 'The body field should have a value after action execution.');

    // With edit_mode off, existing values should be preserved.
    $node2 = Node::create([
      'type' => 'article',
      'title' => 'Test Article 2',
      'body' => [['value' => 'Existing body text']],
    ]);
    $node2->save();
    $action->execute($node2);
    $this->assertEquals('Existing body text', $node2->get('body')->value, 'Existing body should be preserved when edit_mode is off.');

    // With edit_mode on, existing values should be overwritten.
    $this->container->get('entity_type.manager')
      ->getStorage('ai_automator')
      ->load('node.article.body.action')
      ->delete();
    $this->createActionAutomator(['edit_mode' => TRUE]);
    $node3 = Node::create([
      'type' => 'article',
      'title' => 'Test Article 3',
      'body' => [['value' => 'Existing body text']],
    ]);
    $node3->save();
    /** @var \Drupal\ai_automators\Plugin\Action\RunAutomatorAction $editAction */
    $editAction = $this->container->get('plugin.manager.action')
      ->createInstance('ai_automators_run:node.article.body.action');
    $editAction->execute($node3);
    $this->assertNotEquals('Existing body text', $node3->get('body')->value, 'The body should be overwritten when edit_mode is enabled.');
    $this->assertFalse($node3->get('body')->isEmpty());
  }

  /**
   * Tests that access() denies entities with a mismatched bundle.
   */
  public function testBundleMismatchDeniedByAccess(): void {
    $this->createActionAutomator();

    // Create a page node (automator targets article).
    FieldConfig::create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Body',
    ])->save();
    $page = Node::create([
      'type' => 'page',
      'title' => 'Test Page',
      'body' => [['value' => 'Original body']],
    ]);
    $page->save();

    /** @var \Drupal\ai_automators\Plugin\Action\RunAutomatorAction $action */
    $action = $this->container->get('plugin.manager.action')
      ->createInstance('ai_automators_run:node.article.body.action');

    // access() should deny for mismatched bundle.
    $access = $action->access($page, NULL, TRUE);
    $this->assertTrue($access->isForbidden(), 'Access should be forbidden for bundle mismatch.');
  }

}
