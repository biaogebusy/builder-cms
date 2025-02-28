<?php

namespace Drupal\Tests\entity\Kernel\QueryAccess;

use Drupal\entity\QueryAccess\Condition;
use Drupal\entity\QueryAccess\QueryAccessHandler;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Tests the query access handler.
 *
 * Uses the "entity_test_enhanced" entity type, which has no owner.
 * UncacheableQueryAccessHandlerTest uses the "entity_test_enhanced_with_owner"
 * entity type, which has an owner. This ensures both sides (owner and
 * no owner) are covered.
 *
 * @coversDefaultClass \Drupal\entity\QueryAccess\QueryAccessHandler
 * @group entity
 */
class QueryAccessHandlerTest extends EntityKernelTestBase {

  /**
   * The query access handler.
   *
   * @var \Drupal\entity\QueryAccess\QueryAccessHandler
   */
  protected $handler;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity',
    'entity_module_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test_enhanced');

    // Create uid: 1 here so that it's skipped in test cases.
    $admin_user = $this->createUser();

    $entity_type_manager = $this->container->get('entity_type.manager');
    $entity_type = $entity_type_manager->getDefinition('entity_test_enhanced');
    $this->handler = QueryAccessHandler::createInstance($this->container, $entity_type);
  }

  /**
   * @covers ::getConditions
   */
  public function testNoAccess() {
    foreach (['view', 'update', 'duplicate', 'delete'] as $operation) {
      $user = $this->createUser([], ['access content']);
      $conditions = $this->handler->getConditions($operation, $user);
      $this->assertEquals(0, $conditions->count());
      $this->assertEquals(['user.permissions'], $conditions->getCacheContexts());
      $this->assertTrue($conditions->isAlwaysFalse());
    }
  }

  /**
   * @covers ::getConditions
   */
  public function testAdmin() {
    foreach (['view', 'update', 'duplicate', 'delete'] as $operation) {
      $user = $this->createUser([], ['administer entity_test_enhanced']);
      $conditions = $this->handler->getConditions($operation, $user);
      $this->assertEquals(0, $conditions->count());
      $this->assertEquals(['user.permissions'], $conditions->getCacheContexts());
      $this->assertFalse($conditions->isAlwaysFalse());
    }
  }

  /**
   * @covers ::getConditions
   */
  public function testView() {
    // Entity type permission.
    $user = $this->createUser([], ['view entity_test_enhanced']);
    $conditions = $this->handler->getConditions('view', $user);
    $expected_conditions = [
      new Condition('status', '1'),
    ];
    $this->assertEquals(1, $conditions->count());
    $this->assertEquals($expected_conditions, $conditions->getConditions());
    $this->assertEquals(['user.permissions'], $conditions->getCacheContexts());
    $this->assertFalse($conditions->isAlwaysFalse());

    // Bundle permission.
    $user = $this->createUser([], ['view first entity_test_enhanced']);
    $conditions = $this->handler->getConditions('view', $user);
    $expected_conditions = [
      new Condition('type', ['first']),
      new Condition('status', '1'),
    ];
    $this->assertEquals('AND', $conditions->getConjunction());
    $this->assertEquals(2, $conditions->count());
    $this->assertEquals($expected_conditions, $conditions->getConditions());
    $this->assertEquals(['user.permissions'], $conditions->getCacheContexts());
    $this->assertFalse($conditions->isAlwaysFalse());
  }

  /**
   * @covers ::getConditions
   */
  public function testUpdateDuplicateDelete() {
    foreach (['update', 'duplicate', 'delete'] as $operation) {
      $user = $this->createUser([], [
        "$operation first entity_test_enhanced",
        "$operation second entity_test_enhanced",
      ]);
      $conditions = $this->handler->getConditions($operation, $user);
      $expected_conditions = [
        new Condition('type', ['first', 'second']),
      ];
      $this->assertEquals('OR', $conditions->getConjunction());
      $this->assertEquals(1, $conditions->count());
      $this->assertEquals($expected_conditions, $conditions->getConditions());
      $this->assertEquals(['user.permissions'], $conditions->getCacheContexts());
      $this->assertFalse($conditions->isAlwaysFalse());
    }
  }

}
