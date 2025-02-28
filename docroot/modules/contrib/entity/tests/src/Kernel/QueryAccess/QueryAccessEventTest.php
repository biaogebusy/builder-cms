<?php

namespace Drupal\Tests\entity\Kernel\QueryAccess;

use Drupal\entity\QueryAccess\QueryAccessHandler;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Tests the query access event.
 *
 * @group entity
 */
class QueryAccessEventTest extends EntityKernelTestBase {

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
   * Tests the generic event.
   */
  public function testGenericEvent() {
    $entity_type_manager = $this->container->get('entity_type.manager');
    $entity_type = $entity_type_manager->getDefinition('entity_test_enhanced_with_owner');
    $handler = QueryAccessHandler::createInstance($this->container, $entity_type);

    $first_user = $this->createUser(['mail' => 'user9000@example.com']);
    $conditions = $handler->getConditions('view', $first_user);
    $this->assertTrue($conditions->isAlwaysFalse());

    $second_user = $this->createUser(['mail' => 'user9001@example.com']);
    $conditions = $handler->getConditions('view', $second_user);
    $this->assertFalse($conditions->isAlwaysFalse());
  }

  /**
   * Tests the event.
   */
  public function testEvent() {
    // By default, the first user should have full access, and the second
    // user should have no access. The QueryAccessSubscriber flips that.
    $first_user = $this->createUser(['mail' => 'user1@example.com'], ['administer entity_test_enhanced']);
    $second_user = $this->createUser(['mail' => 'user2@example.com']);

    $conditions = $this->handler->getConditions('view', $first_user);
    $this->assertTrue($conditions->isAlwaysFalse());

    $conditions = $this->handler->getConditions('view', $second_user);
    $this->assertFalse($conditions->isAlwaysFalse());
  }

}
