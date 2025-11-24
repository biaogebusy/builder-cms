<?php

namespace Drupal\Tests\entity\Unit;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\entity\EntityAccessControlHandler;
use Drupal\entity\EntityPermissionProvider;
use Drupal\Tests\UnitTestCase;
use Drupal\user\EntityOwnerInterface;
use Prophecy\Argument;

/**
 * @coversDefaultClass \Drupal\entity\EntityAccessControlHandler
 * @group entity
 */
class EntityAccessControlHandlerTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_handler = $this->prophesize(ModuleHandlerInterface::class);
    $module_handler->invokeAll(Argument::any(), Argument::any())->willReturn([]);
    $cache_contexts_manager = $this->prophesize(CacheContextsManager::class);
    $cache_contexts_manager->assertValidTokens(Argument::any())->willReturn(TRUE);

    $container = new ContainerBuilder();
    $container->set('module_handler', $module_handler->reveal());
    $container->set('cache_contexts_manager', $cache_contexts_manager->reveal());
    \Drupal::setContainer($container);
  }

  /**
   * @covers ::checkAccess
   * @covers ::checkEntityPermissions
   * @covers ::checkEntityOwnerPermissions
   * @covers ::checkCreateAccess
   *
   * @dataProvider accessProvider
   */
  public function testAccess(array $entity_mock_args, $operation, array $user_mock_args, $allowed, $cache_contexts) {

    $entity_type = $this->prophesize(ContentEntityTypeInterface::class);
    $entity_type->id()->willReturn('green_entity');
    $entity_type->getAdminPermission()->willReturn('administer green_entity');
    $entity_type->hasHandlerClass('permission_provider')->willReturn(TRUE);
    $entity_type->getHandlerClass('permission_provider')->willReturn(EntityPermissionProvider::class);
    $entity = $this->buildMockEntity(...array_merge([$entity_type->reveal()], $entity_mock_args))->reveal();

    $account = $this->buildMockUser(...$user_mock_args)->reveal();

    $handler = new EntityAccessControlHandler($entity->getEntityType());
    $handler->setStringTranslation($this->getStringTranslationStub());
    $result = $handler->access($entity, $operation, $account, TRUE);
    $this->assertEquals($allowed, $result->isAllowed());
    $this->assertEqualsCanonicalizing($cache_contexts, $result->getCacheContexts());
  }

  /**
   * @covers ::checkCreateAccess
   *
   * @dataProvider createAccessProvider
   */
  public function testCreateAccess($bundle, array $account_mock_args, $allowed, $cache_contexts) {

    $entity_type = $this->prophesize(ContentEntityTypeInterface::class);
    $entity_type->id()->willReturn('green_entity');
    $entity_type->getAdminPermission()->willReturn('administer green_entity');
    $entity_type->hasHandlerClass('permission_provider')->willReturn(TRUE);
    $entity_type->getHandlerClass('permission_provider')->willReturn(EntityPermissionProvider::class);

    $account = $this->buildMockUser(...$account_mock_args)->reveal();

    $handler = new EntityAccessControlHandler($entity_type->reveal());
    $handler->setStringTranslation($this->getStringTranslationStub());
    $result = $handler->createAccess($bundle, $account, [], TRUE);
    $this->assertEquals($allowed, $result->isAllowed());
    $this->assertEquals($cache_contexts, $result->getCacheContexts());
  }

  /**
   * Data provider for testAccess().
   *
   * @return array
   *   A list of testAccess method arguments.
   */
  public static function accessProvider() {
    $entity_mock_args = [6];

    $data = [];
    // Admin permission.
    $admin_user_mock_args = [5, 'administer green_entity'];
    $data['admin user, view'] = [$entity_mock_args, 'view', $admin_user_mock_args, TRUE, ['user.permissions']];
    $data['admin user, update'] = [$entity_mock_args, 'update', $admin_user_mock_args, TRUE, ['user.permissions']];
    $data['admin user, duplicate'] = [$entity_mock_args, 'duplicate', $admin_user_mock_args, TRUE, ['user.permissions']];
    $data['admin user, delete'] = [$entity_mock_args, 'delete', $admin_user_mock_args, TRUE, ['user.permissions']];

    // View, update, duplicate, delete permissions, entity without an owner.
    $second_entity_mock_args = [];
    foreach (['view', 'update', 'duplicate', 'delete'] as $operation) {
      $first_user_mock_args = [6, $operation . ' green_entity'];
      $second_user_mock_args = [7, 'access content'];

      $data["first user, $operation, entity without owner"] = [$second_entity_mock_args, $operation, $first_user_mock_args, TRUE, ['user.permissions']];
      $data["second user, $operation, entity without owner"] = [$second_entity_mock_args, $operation, $second_user_mock_args, FALSE, ['user.permissions']];
    }

    // Update, duplicate, and delete permissions.
    foreach (['update', 'duplicate', 'delete'] as $operation) {
      // Owner, non-owner, user with "any" permission.
      $first_user_mock_args = [6, $operation . ' own green_entity'];
      $second_user_mock_args = [7, $operation . ' own green_entity'];
      $third_user_mock_args = [8, $operation . ' any green_entity'];

      $data["first user, $operation, entity with owner"] = [$entity_mock_args, $operation, $first_user_mock_args, TRUE, ['user', 'user.permissions']];
      $data["second user, $operation, entity with owner"] = [$entity_mock_args, $operation, $second_user_mock_args, FALSE, ['user', 'user.permissions']];
      $data["third user, $operation, entity with owner"] = [$entity_mock_args, $operation, $third_user_mock_args, TRUE, ['user.permissions']];
    }

    // View permissions.
    $first_user_mock_args = [9, 'view green_entity'];
    $second_user_mock_args = [10, 'view first green_entity'];
    $third_user_mock_args = [14, 'view own unpublished green_entity'];
    $fourth_user_mock_args = [14, 'access content'];

    $first_entity_mock_args = [1, 'first'];
    $second_entity_mock_args = [1, 'second'];
    $third_entity_mock_args = [14, 'first', FALSE];

    // The first user can view the two published entities.
    $data['first user, view, first entity'] = [$first_entity_mock_args, 'view', $first_user_mock_args, TRUE, ['user.permissions']];
    $data['first user, view, second entity'] = [$second_entity_mock_args, 'view', $first_user_mock_args, TRUE, ['user.permissions']];
    $data['first user, view, third entity'] = [$third_entity_mock_args, 'view', $first_user_mock_args, FALSE, ['user']];

    // The second user can only view published entities of bundle "first".
    $data['second user, view, first entity'] = [$first_entity_mock_args, 'view', $second_user_mock_args, TRUE, ['user.permissions']];
    $data['second user, view, second entity'] = [$second_entity_mock_args, 'view', $second_user_mock_args, FALSE, ['user.permissions']];
    $data['second user, view, third entity'] = [$third_entity_mock_args, 'view', $second_user_mock_args, FALSE, ['user']];

    // The third user can view their own unpublished entity.
    $data['third user, view, first entity'] = [$first_entity_mock_args, 'view', $third_user_mock_args, FALSE, ['user.permissions']];
    $data['third user, view, second entity'] = [$second_entity_mock_args, 'view', $third_user_mock_args, FALSE, ['user.permissions']];
    $data['third user, view, third entity'] = [$third_entity_mock_args, 'view', $third_user_mock_args, TRUE, ['user', 'user.permissions']];

    // The fourth user can't view anything.
    $data['fourth user, view, first entity'] = [$first_entity_mock_args, 'view', $fourth_user_mock_args, FALSE, ['user.permissions']];
    $data['fourth user, view, second entity'] = [$second_entity_mock_args, 'view', $fourth_user_mock_args, FALSE, ['user.permissions']];
    $data['fourth user, view, third entity'] = [$third_entity_mock_args, 'view', $fourth_user_mock_args, FALSE, ['user', 'user.permissions']];

    return $data;
  }

  /**
   * Data provider for testCreateAccess().
   *
   * @return array
   *   A list of testCreateAccess method arguments.
   */
  public static function createAccessProvider() {
    $data = [];

    // User with the admin permission.
    $account_mock_args = ['6', 'administer green_entity'];
    $data['admin user'] = [NULL, $account_mock_args, TRUE, ['user.permissions']];

    // Ordinary user.
    $account_mock_args = ['6', 'create green_entity'];
    $data['regular user'] = [NULL, $account_mock_args, TRUE, ['user.permissions']];

    // Ordinary user, entity with a bundle.
    $account_mock_args = ['6', 'create first_bundle green_entity'];
    $data['regular user, entity with bundle'] = ['first_bundle', $account_mock_args, TRUE, ['user.permissions']];

    // User with no permissions.
    $account_mock_args = ['6', 'access content'];
    $data['user without permission'] = [NULL, $account_mock_args, FALSE, ['user.permissions']];

    return $data;
  }

  /**
   * Builds a mock entity.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param string $owner_id
   *   The owner ID.
   * @param string $bundle
   *   The bundle.
   * @param bool $published
   *   Whether the entity is published.
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   *   The entity mock.
   */
  protected function buildMockEntity(EntityTypeInterface $entity_type, $owner_id = NULL, $bundle = NULL, $published = NULL) {
    $langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED;
    $entity = $this->prophesize(ContentEntityInterface::class);
    if (isset($published)) {
      $entity->willImplement(EntityPublishedInterface::class);
    }
    if ($owner_id) {
      $entity->willImplement(EntityOwnerInterface::class);
    }
    if (isset($published)) {
      $entity->isPublished()->willReturn($published);
    }
    if ($owner_id) {
      $entity->getOwnerId()->willReturn($owner_id);
    }

    $entity->bundle()->willReturn($bundle ?: $entity_type->id());
    $entity->isNew()->willReturn(FALSE);
    $entity->uuid()->willReturn('fake uuid');
    $entity->id()->willReturn('fake id');
    $entity->getRevisionId()->willReturn(NULL);
    $entity->language()->willReturn(new Language(['id' => $langcode]));
    $entity->getEntityTypeId()->willReturn($entity_type->id());
    $entity->getEntityType()->willReturn($entity_type);
    $entity->getCacheContexts()->willReturn([]);
    $entity->getCacheTags()->willReturn([]);
    $entity->getCacheMaxAge()->willReturn(Cache::PERMANENT);
    $entity->isDefaultRevision()->willReturn(TRUE);

    return $entity;
  }

  /**
   * Builds a mock user.
   *
   * @param int $uid
   *   The user ID.
   * @param string $permission
   *   The permission to grant.
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   *   The user mock.
   */
  protected function buildMockUser($uid, $permission) {
    $account = $this->prophesize(AccountInterface::class);
    $account->id()->willReturn($uid);
    $account->hasPermission($permission)->willReturn(TRUE);
    $account->hasPermission(Argument::any())->willReturn(FALSE);

    return $account;
  }

}
