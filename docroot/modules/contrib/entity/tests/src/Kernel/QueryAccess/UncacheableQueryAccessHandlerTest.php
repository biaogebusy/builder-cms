<?php

namespace Drupal\Tests\entity\Kernel\QueryAccess;

use Drupal\entity\QueryAccess\Condition;
use Drupal\entity\QueryAccess\ConditionGroup;
use Drupal\entity\QueryAccess\UncacheableQueryAccessHandler;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Tests the uncacheable query access handler.
 *
 * Uses the "entity_test_enhanced_with_owner" entity type, which has an owner.
 * QueryAccessHandlerTest uses the "entity_test_enhanced" entity type, which
 * has no owner. This ensures both sides (owner and no owner) are covered.
 *
 * @coversDefaultClass \Drupal\entity\QueryAccess\UncacheableQueryAccessHandler
 * @group entity
 */
class UncacheableQueryAccessHandlerTest extends EntityKernelTestBase {

  /**
   * The query access handler.
   *
   * @var \Drupal\entity\QueryAccess\UncacheableQueryAccessHandler
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

    $this->installEntitySchema('entity_test_enhanced_with_owner');

    // Create uid: 1 here so that it's skipped in test cases.
    $admin_user = $this->createUser();

    $entity_type_manager = $this->container->get('entity_type.manager');
    $entity_type = $entity_type_manager->getDefinition('entity_test_enhanced_with_owner');
    $this->handler = UncacheableQueryAccessHandler::createInstance($this->container, $entity_type);
  }

  /**
   * @covers ::getConditions
   */
  public function testNoAccess() {
    foreach (['view', 'update', 'delete'] as $operation) {
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
    foreach (['view', 'update', 'delete'] as $operation) {
      $user = $this->createUser([], ['administer entity_test_enhanced_with_owner']);
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
    // Any permission.
    $user = $this->createUser([], ['view any entity_test_enhanced_with_owner']);
    $conditions = $this->handler->getConditions('view', $user);
    $expected_conditions = [
      new Condition('status', '1'),
    ];
    $this->assertEquals(1, $conditions->count());
    $this->assertEquals($expected_conditions, $conditions->getConditions());
    $this->assertEquals(['user.permissions'], $conditions->getCacheContexts());
    $this->assertFalse($conditions->isAlwaysFalse());

    // Own permission.
    $user = $this->createUser([], ['view own entity_test_enhanced_with_owner']);
    $conditions = $this->handler->getConditions('view', $user);
    $expected_conditions = [
      new Condition('user_id', $user->id()),
      new Condition('status', '1'),
    ];
    $this->assertEquals('AND', $conditions->getConjunction());
    $this->assertEquals(2, $conditions->count());
    $this->assertEquals($expected_conditions, $conditions->getConditions());
    $this->assertEqualsCanonicalizing(['user', 'user.permissions'], $conditions->getCacheContexts());
    $this->assertFalse($conditions->isAlwaysFalse());

    // Any permission for the first bundle, own permission for the second.
    $user = $this->createUser([], [
      'view any first entity_test_enhanced_with_owner',
      'view own second entity_test_enhanced_with_owner',
    ]);
    $conditions = $this->handler->getConditions('view', $user);
    $expected_conditions = [
      (new ConditionGroup('OR'))
        ->addCacheContexts(['user', 'user.permissions'])
        ->addCondition('type', ['first'])
        ->addCondition((new ConditionGroup('AND'))
          ->addCondition('user_id', $user->id())
          ->addCondition('type', ['second'])
        ),
      new Condition('status', '1'),
    ];
    $this->assertEquals('AND', $conditions->getConjunction());
    $this->assertEquals(2, $conditions->count());
    $this->assertEqualsCanonicalizing($expected_conditions, $conditions->getConditions());
    $this->assertEqualsCanonicalizing(['user', 'user.permissions'], $conditions->getCacheContexts());
    $this->assertFalse($conditions->isAlwaysFalse());

    // View own unpublished permission.
    $user = $this->createUser([], ['view own unpublished entity_test_enhanced_with_owner']);
    $conditions = $this->handler->buildConditions('view', $user);
    $expected_conditions = [
      new Condition('user_id', $user->id()),
      new Condition('status', '0'),
    ];
    $this->assertEquals(2, $conditions->count());
    $this->assertEquals($expected_conditions, $conditions->getConditions());
    $this->assertEquals(['user'], $conditions->getCacheContexts());
    $this->assertFalse($conditions->isAlwaysFalse());

    // Both view any and view own unpublished permissions.
    $user = $this->createUser([], [
      'view any entity_test_enhanced_with_owner',
      'view own unpublished entity_test_enhanced_with_owner',
    ]);
    $conditions = $this->handler->buildConditions('view', $user);
    $expected_conditions = [
      new Condition('status', '1'),
      (new ConditionGroup('AND'))
        ->addCondition('user_id', $user->id())
        ->addCondition('status', '0')
        ->addCacheContexts(['user']),
    ];
    $this->assertEquals(2, $conditions->count());
    $this->assertEquals($expected_conditions, $conditions->getConditions());
    $this->assertEqualsCanonicalizing(['user', 'user.permissions'], $conditions->getCacheContexts());
    $this->assertFalse($conditions->isAlwaysFalse());
  }

  /**
   * @covers ::getConditions
   */
  public function testUpdateDuplicateDelete() {
    foreach (['update', 'duplicate', 'delete'] as $operation) {
      // Any permission for the first bundle, own permission for the second.
      $user = $this->createUser([], [
        "$operation any first entity_test_enhanced_with_owner",
        "$operation own second entity_test_enhanced_with_owner",
      ]);
      $conditions = $this->handler->getConditions($operation, $user);
      $expected_conditions = [
        new Condition('type', ['first']),
        (new ConditionGroup('AND'))
          ->addCondition('user_id', $user->id())
          ->addCondition('type', ['second']),
      ];
      $this->assertEquals('OR', $conditions->getConjunction());
      $this->assertEquals(2, $conditions->count());
      $this->assertEquals($expected_conditions, $conditions->getConditions());
      $this->assertEqualsCanonicalizing(['user', 'user.permissions'], $conditions->getCacheContexts());
      $this->assertFalse($conditions->isAlwaysFalse());
    }
  }

}
