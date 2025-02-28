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
use Drupal\entity\UncacheableEntityAccessControlHandler;
use Drupal\entity\UncacheableEntityPermissionProvider;
use Drupal\Tests\UnitTestCase;
use Drupal\user\EntityOwnerInterface;
use Prophecy\Argument;

/**
 * @coversDefaultClass \Drupal\entity\UncacheableEntityAccessControlHandler
 * @group entity
 */
class UncacheableEntityAccessControlHandlerTest extends UnitTestCase {

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
  public function testAccess(EntityInterface $entity, $operation, $account, $allowed) {
    $handler = new UncacheableEntityAccessControlHandler($entity->getEntityType());
    $handler->setStringTranslation($this->getStringTranslationStub());
    $result = $handler->access($entity, $operation, $account);
    $this->assertEquals($allowed, $result);
  }

  /**
   * @covers ::checkCreateAccess
   *
   * @dataProvider createAccessProvider
   */
  public function testCreateAccess(EntityTypeInterface $entity_type, $bundle, $account, $allowed) {
    $handler = new UncacheableEntityAccessControlHandler($entity_type);
    $handler->setStringTranslation($this->getStringTranslationStub());
    $result = $handler->createAccess($bundle, $account);
    $this->assertEquals($allowed, $result);
  }

  /**
   * Data provider for testAccess().
   *
   * @return array
   *   A list of testAccess method arguments.
   */
  public function accessProvider() {
    $entity_type = $this->prophesize(ContentEntityTypeInterface::class);
    $entity_type->id()->willReturn('green_entity');
    $entity_type->getAdminPermission()->willReturn('administer green_entity');
    $entity_type->hasHandlerClass('permission_provider')->willReturn(TRUE);
    $entity_type->getHandlerClass('permission_provider')->willReturn(UncacheableEntityPermissionProvider::class);
    $entity = $this->buildMockEntity($entity_type->reveal(), 6);

    $data = [];
    // Admin permission.
    $admin_user = $this->buildMockUser(5, 'administer green_entity');
    $data[] = [$entity->reveal(), 'view', $admin_user->reveal(), TRUE];
    $data[] = [$entity->reveal(), 'update', $admin_user->reveal(), TRUE];
    $data[] = [$entity->reveal(), 'duplicate', $admin_user->reveal(), TRUE];
    $data[] = [$entity->reveal(), 'delete', $admin_user->reveal(), TRUE];

    // View, update, duplicate, delete permissions, entity without an owner.
    $second_entity = $this->buildMockEntity($entity_type->reveal());
    foreach (['view', 'update', 'duplicate', 'delete'] as $operation) {
      $first_user = $this->buildMockUser(6, $operation . ' green_entity');
      $second_user = $this->buildMockUser(7, 'access content');

      $data[] = [$second_entity->reveal(), $operation, $first_user->reveal(), TRUE];
      $data[] = [$second_entity->reveal(), $operation, $second_user->reveal(), FALSE];
    }

    // View, update, duplicate, delete permissions.
    foreach (['view', 'update', 'duplicate', 'delete'] as $operation) {
      // Owner, non-owner, user with "any" permission.
      $first_user = $this->buildMockUser(6, $operation . ' own green_entity');
      $second_user = $this->buildMockUser(7, $operation . ' own green_entity');
      $third_user = $this->buildMockUser(8, $operation . ' any green_entity');

      $data[] = [$entity->reveal(), $operation, $first_user->reveal(), TRUE];
      $data[] = [$entity->reveal(), $operation, $second_user->reveal(), FALSE];
      $data[] = [$entity->reveal(), $operation, $third_user->reveal(), TRUE];
    }

    // Per bundle and unpublished view permissions.
    $first_user = $this->buildMockUser(11, 'view any first green_entity');
    $second_user = $this->buildMockUser(12, 'view own first green_entity');
    $third_user = $this->buildMockUser(13, 'view own unpublished green_entity');

    $first_entity = $this->buildMockEntity($entity_type->reveal(), 9999, 'first');
    $second_entity = $this->buildMockEntity($entity_type->reveal(), 12, 'first');
    $third_entity = $this->buildMockEntity($entity_type->reveal(), 9999, 'second');
    $fourth_entity = $this->buildMockEntity($entity_type->reveal(), 10, 'second');
    $fifth_entity = $this->buildMockEntity($entity_type->reveal(), 13, 'first', FALSE);

    // The first user can view the two entities of bundle "first".
    $data[] = [$first_entity->reveal(), 'view', $first_user->reveal(), TRUE];
    $data[] = [$second_entity->reveal(), 'view', $first_user->reveal(), TRUE];
    $data[] = [$third_entity->reveal(), 'view', $first_user->reveal(), FALSE];
    $data[] = [$fourth_entity->reveal(), 'view', $first_user->reveal(), FALSE];
    $data[] = [$fifth_entity->reveal(), 'view', $first_user->reveal(), FALSE];

    // The second user can view their own entity of bundle "first".
    $data[] = [$first_entity->reveal(), 'view', $second_user->reveal(), FALSE];
    $data[] = [$second_entity->reveal(), 'view', $second_user->reveal(), TRUE];
    $data[] = [$third_entity->reveal(), 'view', $second_user->reveal(), FALSE];
    $data[] = [$fourth_entity->reveal(), 'view', $second_user->reveal(), FALSE];
    $data[] = [$fourth_entity->reveal(), 'view', $second_user->reveal(), FALSE];
    $data[] = [$fifth_entity->reveal(), 'view', $second_user->reveal(), FALSE];

    // The third user can only view their own unpublished entity.
    $data[] = [$first_entity->reveal(), 'view', $third_user->reveal(), FALSE];
    $data[] = [$second_entity->reveal(), 'view', $third_user->reveal(), FALSE];
    $data[] = [$third_entity->reveal(), 'view', $third_user->reveal(), FALSE];
    $data[] = [$fourth_entity->reveal(), 'view', $third_user->reveal(), FALSE];
    $data[] = [$fourth_entity->reveal(), 'view', $third_user->reveal(), FALSE];
    $data[] = [$fifth_entity->reveal(), 'view', $third_user->reveal(), TRUE];

    return $data;
  }

  /**
   * Data provider for testCreateAccess().
   *
   * @return array
   *   A list of testCreateAccess method arguments.
   */
  public function createAccessProvider() {
    $data = [];

    $entity_type = $this->prophesize(ContentEntityTypeInterface::class);
    $entity_type->id()->willReturn('green_entity');
    $entity_type->getAdminPermission()->willReturn('administer green_entity');
    $entity_type->hasHandlerClass('permission_provider')->willReturn(TRUE);
    $entity_type->getHandlerClass('permission_provider')->willReturn(UncacheableEntityPermissionProvider::class);

    // User with the admin permission.
    $account = $this->buildMockUser('6', 'administer green_entity');
    $data[] = [$entity_type->reveal(), NULL, $account->reveal(), TRUE];

    // Ordinary user.
    $account = $this->buildMockUser('6', 'create green_entity');
    $data[] = [$entity_type->reveal(), NULL, $account->reveal(), TRUE];

    // Ordinary user, entity with a bundle.
    $account = $this->buildMockUser('6', 'create first_bundle green_entity');
    $data[] = [$entity_type->reveal(), 'first_bundle', $account->reveal(), TRUE];

    // User with no permissions.
    $account = $this->buildMockUser('6', 'access content');
    $data[] = [$entity_type->reveal(), NULL, $account->reveal(), FALSE];

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
