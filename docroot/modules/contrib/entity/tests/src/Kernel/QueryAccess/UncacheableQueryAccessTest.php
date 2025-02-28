<?php

namespace Drupal\Tests\entity\Kernel\QueryAccess;

use Drupal\entity_module_test\Entity\EnhancedEntityWithOwner;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\views\Tests\ViewResultAssertionTrait;
use Drupal\views\Views;

/**
 * Test uncacheable query access filtering for EntityQuery and Views.
 *
 * @group entity
 *
 * @see \Drupal\entity\QueryAccess\UncacheableQueryAccessHandler
 * @see \Drupal\entity\QueryAccess\EntityQueryAlter
 * @see \Drupal\entity\QueryAccess\ViewsQueryAlter
 */
class UncacheableQueryAccessTest extends EntityKernelTestBase {

  use ViewResultAssertionTrait;

  /**
   * The test entities.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface[]
   */
  protected $entities;

  /**
   * The entity_test_enhanced storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity',
    'entity_module_test',
    'user',
    'views',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test_enhanced_with_owner');
    $this->installConfig(['entity_module_test']);

    // Create uid: 1 here so that it's skipped in test cases.
    $admin_user = $this->createUser();

    $first_entity = EnhancedEntityWithOwner::create([
      'type' => 'first',
      'name' => 'First',
      'status' => 1,
    ]);
    $first_entity->save();

    $first_entity->set('name', 'First!');
    $first_entity->set('status', 0);
    $first_entity->setNewRevision(TRUE);
    $first_entity->save();

    $second_entity = EnhancedEntityWithOwner::create([
      'type' => 'first',
      'name' => 'Second',
      'status' => 0,
    ]);
    $second_entity->save();

    $second_entity->set('name', 'Second!');
    $second_entity->set('status', 1);
    $second_entity->setNewRevision(TRUE);
    $second_entity->save();

    $third_entity = EnhancedEntityWithOwner::create([
      'type' => 'second',
      'name' => 'Third',
      'status' => 1,
    ]);
    $third_entity->save();

    $third_entity->set('name', 'Third!');
    $third_entity->setNewRevision(TRUE);
    $third_entity->save();

    $this->entities = [$first_entity, $second_entity, $third_entity];
    $this->storage = $this->entityTypeManager->getStorage('entity_test_enhanced_with_owner');
  }

  /**
   * Tests EntityQuery filtering.
   */
  public function testEntityQuery() {
    // Admin permission, full access.
    $admin_user = $this->createUser([], ['administer entity_test_enhanced_with_owner']);
    $this->container->get('current_user')->setAccount($admin_user);

    $result = $this->storage->getQuery()->sort('id')->accessCheck(TRUE)->execute();
    $this->assertEquals([
      $this->entities[0]->id(),
      $this->entities[1]->id(),
      $this->entities[2]->id(),
    ], array_values($result));

    // No view permissions, no access.
    $user = $this->createUser([], ['access content']);
    $this->container->get('current_user')->setAccount($user);

    $result = $this->storage->getQuery()->accessCheck(TRUE)->execute();
    $this->assertEmpty($result);

    // View own (published-only).
    $user = $this->createUser([], ['view own entity_test_enhanced_with_owner']);
    $this->container->get('current_user')->setAccount($user);

    $this->entities[0]->set('user_id', $user->id());
    $this->entities[0]->save();
    $this->entities[1]->set('user_id', $user->id());
    $this->entities[1]->save();

    $result = $this->storage->getQuery()->sort('id')->accessCheck(TRUE)->execute();
    $this->assertEquals([
      $this->entities[1]->id(),
    ], array_values($result));

    // View any (published-only).
    $user = $this->createUser([], ['view any entity_test_enhanced_with_owner']);
    $this->container->get('current_user')->setAccount($user);

    $result = $this->storage->getQuery()->sort('id')->accessCheck(TRUE)->execute();
    $this->assertEquals([
      $this->entities[1]->id(),
      $this->entities[2]->id(),
    ], array_values($result));

    // View own unpublished.
    $user = $this->createUser([], ['view own unpublished entity_test_enhanced_with_owner']);
    $this->container->get('current_user')->setAccount($user);

    $this->entities[0]->set('user_id', $user->id());
    $this->entities[0]->save();
    $this->entities[1]->set('user_id', $user->id());
    $this->entities[1]->save();

    $result = $this->storage->getQuery()->sort('id')->accessCheck(TRUE)->execute();
    $this->assertEquals([
      $this->entities[0]->id(),
    ], array_values($result));

    // View own unpublished + view any (published-only).
    $user = $this->createUser([], [
      'view own unpublished entity_test_enhanced_with_owner',
      'view any entity_test_enhanced_with_owner',
    ]);
    $this->container->get('current_user')->setAccount($user);

    $this->entities[0]->set('user_id', $user->id());
    $this->entities[0]->save();

    $result = $this->storage->getQuery()->sort('id')->accessCheck(TRUE)->execute();
    $this->assertEquals([
      $this->entities[0]->id(),
      $this->entities[1]->id(),
      $this->entities[2]->id(),
    ], array_values($result));

    // View own $first_bundle + View any $second_bundle.
    $user = $this->createUser([], [
      'view own first entity_test_enhanced_with_owner',
      'view any second entity_test_enhanced_with_owner',
    ]);
    $this->container->get('current_user')->setAccount($user);

    $this->entities[1]->set('user_id', $user->id());
    $this->entities[1]->save();

    $result = $this->storage->getQuery()->sort('id')->accessCheck(TRUE)->execute();
    $this->assertEquals([
      $this->entities[1]->id(),
      $this->entities[2]->id(),
    ], array_values($result));
  }

  /**
   * Tests EntityQuery filtering when all revisions are queried.
   */
  public function testEntityQueryWithRevisions() {
    // Admin permission, full access.
    $admin_user = $this->createUser([], ['administer entity_test_enhanced_with_owner']);
    $this->container->get('current_user')->setAccount($admin_user);

    $result = $this->storage->getQuery()
      ->allRevisions()
      ->sort('id')
      ->accessCheck(TRUE)
      ->execute();
    $this->assertEquals([
      '1' => $this->entities[0]->id(),
      '2' => $this->entities[0]->id(),
      '3' => $this->entities[1]->id(),
      '4' => $this->entities[1]->id(),
      '5' => $this->entities[2]->id(),
      '6' => $this->entities[2]->id(),
    ], $result);

    // No view permissions, no access.
    $user = $this->createUser([], ['access content']);
    $this->container->get('current_user')->setAccount($user);

    $result = $this->storage->getQuery()->allRevisions()->accessCheck(TRUE)->execute();
    $this->assertEmpty($result);

    // View own (published-only).
    $user = $this->createUser([], ['view own entity_test_enhanced_with_owner']);
    $this->container->get('current_user')->setAccount($user);

    // The user_id field is not revisionable, which means that updating it
    // will modify both revisions for each entity.
    $this->entities[0]->set('user_id', $user->id());
    $this->entities[0]->save();
    $this->entities[1]->set('user_id', $user->id());
    $this->entities[1]->save();

    $result = $this->storage->getQuery()
      ->allRevisions()
      ->sort('id')
      ->accessCheck(TRUE)
      ->execute();
    $this->assertEquals([
      '1' => $this->entities[0]->id(),
      '4' => $this->entities[1]->id(),
    ], $result);

    // View any (published-only).
    $user = $this->createUser([], ['view any entity_test_enhanced_with_owner']);
    $this->container->get('current_user')->setAccount($user);

    $result = $this->storage->getQuery()
      ->allRevisions()
      ->sort('id')
      ->accessCheck(TRUE)
      ->execute();
    $this->assertEquals([
      '1' => $this->entities[0]->id(),
      '4' => $this->entities[1]->id(),
      '5' => $this->entities[2]->id(),
      '6' => $this->entities[2]->id(),
    ], $result);

    // View own unpublished.
    $user = $this->createUser([], ['view own unpublished entity_test_enhanced_with_owner']);
    $this->container->get('current_user')->setAccount($user);

    $this->entities[0]->set('user_id', $user->id());
    $this->entities[0]->save();
    $this->entities[1]->set('user_id', $user->id());
    $this->entities[1]->save();

    $result = $this->storage->getQuery()
      ->allRevisions()
      ->sort('id')
      ->accessCheck(TRUE)
      ->execute();
    $this->assertEquals([
      '2' => $this->entities[0]->id(),
      '3' => $this->entities[1]->id(),
    ], $result);

    // View own unpublished + view any (published-only).
    $user = $this->createUser([], [
      'view own unpublished entity_test_enhanced_with_owner',
      'view any entity_test_enhanced_with_owner',
    ]);
    $this->container->get('current_user')->setAccount($user);

    $this->entities[0]->set('user_id', $user->id());
    $this->entities[0]->save();

    $result = $this->storage->getQuery()
      ->allRevisions()
      ->sort('id')
      ->accessCheck(TRUE)
      ->execute();
    $this->assertEquals([
      '1' => $this->entities[0]->id(),
      '2' => $this->entities[0]->id(),
      '4' => $this->entities[1]->id(),
      '5' => $this->entities[2]->id(),
      '6' => $this->entities[2]->id(),
    ], $result);

    // View own $first_bundle + View any $second_bundle.
    $user = $this->createUser([], [
      'view own first entity_test_enhanced_with_owner',
      'view any second entity_test_enhanced_with_owner',
    ]);
    $this->container->get('current_user')->setAccount($user);

    $this->entities[1]->set('user_id', $user->id());
    $this->entities[1]->save();

    $result = $this->storage->getQuery()
      ->allRevisions()
      ->sort('id')
      ->accessCheck(TRUE)
      ->execute();
    $this->assertEquals([
      '4' => $this->entities[1]->id(),
      '5' => $this->entities[2]->id(),
      '6' => $this->entities[2]->id(),
    ], $result);
  }

  /**
   * Tests Views filtering.
   */
  public function testViews() {
    // Admin permission, full access.
    $admin_user = $this->createUser([], ['administer entity_test_enhanced_with_owner']);
    $this->container->get('current_user')->setAccount($admin_user);

    $view = Views::getView('entity_test_enhanced_with_owner');
    $view->execute();
    $this->assertIdenticalResultset($view, [
      ['id' => $this->entities[0]->id()],
      ['id' => $this->entities[1]->id()],
      ['id' => $this->entities[2]->id()],
    ], ['id' => 'id']);

    // No view permissions, no access.
    $user = $this->createUser([], ['access content']);
    $this->container->get('current_user')->setAccount($user);

    $view = Views::getView('entity_test_enhanced_with_owner');
    $view->execute();
    $this->assertIdenticalResultset($view, []);

    // View own (published-only).
    $user = $this->createUser([], ['view own entity_test_enhanced_with_owner']);
    $this->container->get('current_user')->setAccount($user);

    $this->entities[0]->set('user_id', $user->id());
    $this->entities[0]->save();
    $this->entities[1]->set('user_id', $user->id());
    $this->entities[1]->save();

    $view = Views::getView('entity_test_enhanced_with_owner');
    $view->execute();
    $this->assertIdenticalResultset($view, [
      ['id' => $this->entities[1]->id()],
    ], ['id' => 'id']);

    // View any (published-only).
    $user = $this->createUser([], ['view any entity_test_enhanced_with_owner']);
    $this->container->get('current_user')->setAccount($user);

    $view = Views::getView('entity_test_enhanced_with_owner');
    $view->execute();
    $this->assertIdenticalResultset($view, [
      ['id' => $this->entities[1]->id()],
      ['id' => $this->entities[2]->id()],
    ], ['id' => 'id']);

    // View own unpublished.
    $user = $this->createUser([], ['view own unpublished entity_test_enhanced_with_owner']);
    $this->container->get('current_user')->setAccount($user);

    $this->entities[0]->set('user_id', $user->id());
    $this->entities[0]->save();
    $this->entities[1]->set('user_id', $user->id());
    $this->entities[1]->save();

    $view = Views::getView('entity_test_enhanced_with_owner');
    $view->execute();
    $this->assertIdenticalResultset($view, [
      ['id' => $this->entities[0]->id()],
    ], ['id' => 'id']);

    // View own unpublished + view any (published-only).
    $user = $this->createUser([], [
      'view own unpublished entity_test_enhanced_with_owner',
      'view any entity_test_enhanced_with_owner',
    ]);
    $this->container->get('current_user')->setAccount($user);

    $this->entities[0]->set('user_id', $user->id());
    $this->entities[0]->save();

    $view = Views::getView('entity_test_enhanced_with_owner');
    $view->execute();
    $this->assertIdenticalResultset($view, [
      ['id' => $this->entities[0]->id()],
      ['id' => $this->entities[1]->id()],
      ['id' => $this->entities[2]->id()],
    ], ['id' => 'id']);

    // View own $first_bundle + View any $second_bundle.
    $user = $this->createUser([], [
      'view own first entity_test_enhanced_with_owner',
      'view any second entity_test_enhanced_with_owner',
    ]);
    $this->container->get('current_user')->setAccount($user);

    $this->entities[1]->set('user_id', $user->id());
    $this->entities[1]->save();

    $view = Views::getView('entity_test_enhanced_with_owner');
    $view->execute();
    $this->assertIdenticalResultset($view, [
      ['id' => $this->entities[1]->id()],
      ['id' => $this->entities[2]->id()],
    ], ['id' => 'id']);
  }

  /**
   * Tests Views filtering when all revisions are queried.
   */
  public function testViewsWithRevisions() {
    // Admin permission, full access.
    $admin_user = $this->createUser([], ['administer entity_test_enhanced_with_owner']);
    $this->container->get('current_user')->setAccount($admin_user);

    $view = Views::getView('entity_test_enhanced_with_owner_revisions');
    $view->execute();
    $this->assertIdenticalResultset($view, [
      ['vid' => '1', 'id' => $this->entities[0]->id()],
      ['vid' => '2', 'id' => $this->entities[0]->id()],
      ['vid' => '3', 'id' => $this->entities[1]->id()],
      ['vid' => '4', 'id' => $this->entities[1]->id()],
      ['vid' => '5', 'id' => $this->entities[2]->id()],
      ['vid' => '6', 'id' => $this->entities[2]->id()],
    ], ['vid' => 'vid']);

    // No view permissions, no access.
    $user = $this->createUser([], ['access content']);
    $this->container->get('current_user')->setAccount($user);

    $view = Views::getView('entity_test_enhanced_with_owner_revisions');
    $view->execute();
    $this->assertIdenticalResultset($view, []);

    // View own (published-only).
    $user = $this->createUser([], ['view own entity_test_enhanced_with_owner']);
    $this->container->get('current_user')->setAccount($user);

    $this->entities[0]->set('user_id', $user->id());
    $this->entities[0]->save();
    $this->entities[1]->set('user_id', $user->id());
    $this->entities[1]->save();

    $view = Views::getView('entity_test_enhanced_with_owner_revisions');
    $view->execute();
    $this->assertIdenticalResultset($view, [
      ['vid' => '1', 'id' => $this->entities[0]->id()],
      ['vid' => '4', 'id' => $this->entities[1]->id()],
    ], ['vid' => 'vid']);

    // View any (published-only).
    $user = $this->createUser([], ['view any entity_test_enhanced_with_owner']);
    $this->container->get('current_user')->setAccount($user);

    $view = Views::getView('entity_test_enhanced_with_owner_revisions');
    $view->execute();
    $this->assertIdenticalResultset($view, [
      ['vid' => '1', 'id' => $this->entities[0]->id()],
      ['vid' => '4', 'id' => $this->entities[1]->id()],
      ['vid' => '5', 'id' => $this->entities[2]->id()],
      ['vid' => '6', 'id' => $this->entities[2]->id()],
    ], ['vid' => 'vid']);

    // View own unpublished.
    $user = $this->createUser([], ['view own unpublished entity_test_enhanced_with_owner']);
    $this->container->get('current_user')->setAccount($user);

    $this->entities[0]->set('user_id', $user->id());
    $this->entities[0]->save();
    $this->entities[1]->set('user_id', $user->id());
    $this->entities[1]->save();

    $view = Views::getView('entity_test_enhanced_with_owner_revisions');
    $view->execute();
    $this->assertIdenticalResultset($view, [
      ['vid' => '2', 'id' => $this->entities[0]->id()],
      ['vid' => '3', 'id' => $this->entities[1]->id()],
    ], ['vid' => 'vid']);

    // View own unpublished + view any (published-only).
    $user = $this->createUser([], [
      'view own unpublished entity_test_enhanced_with_owner',
      'view any entity_test_enhanced_with_owner',
    ]);
    $this->container->get('current_user')->setAccount($user);

    $this->entities[0]->set('user_id', $user->id());
    $this->entities[0]->save();

    $view = Views::getView('entity_test_enhanced_with_owner_revisions');
    $view->execute();
    $this->assertIdenticalResultset($view, [
      ['vid' => '1', 'id' => $this->entities[0]->id()],
      ['vid' => '2', 'id' => $this->entities[0]->id()],
      ['vid' => '4', 'id' => $this->entities[1]->id()],
      ['vid' => '5', 'id' => $this->entities[2]->id()],
      ['vid' => '6', 'id' => $this->entities[2]->id()],
    ], ['vid' => 'vid']);

    // View own $first_bundle + View any $second_bundle.
    $user = $this->createUser([], [
      'view own first entity_test_enhanced_with_owner',
      'view any second entity_test_enhanced_with_owner',
    ]);
    $this->container->get('current_user')->setAccount($user);

    $this->entities[1]->set('user_id', $user->id());
    $this->entities[1]->save();

    $view = Views::getView('entity_test_enhanced_with_owner_revisions');
    $view->execute();
    $this->assertIdenticalResultset($view, [
      ['vid' => '4', 'id' => $this->entities[1]->id()],
      ['vid' => '5', 'id' => $this->entities[2]->id()],
      ['vid' => '6', 'id' => $this->entities[2]->id()],
    ], ['vid' => 'vid']);
  }

}
