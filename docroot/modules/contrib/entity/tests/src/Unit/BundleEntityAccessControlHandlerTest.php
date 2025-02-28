<?php

namespace Drupal\Tests\entity\Unit;

use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\entity\BundleEntityAccessControlHandler;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;

/**
 * @coversDefaultClass \Drupal\entity\BundleEntityAccessControlHandler
 * @group entity
 */
class BundleEntityAccessControlHandlerTest extends UnitTestCase {

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
   *
   * @dataProvider accessProvider
   */
  public function testAccess(EntityInterface $entity, $operation, $account, $allowed) {
    $handler = new BundleEntityAccessControlHandler($entity->getEntityType());
    $handler->setStringTranslation($this->getStringTranslationStub());
    $result = $handler->access($entity, $operation, $account);
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
    $entity_type->id()->willReturn('green_entity_bundle');
    $entity_type->getBundleOf()->willReturn('green_entity');
    $entity_type->getAdminPermission()->willReturn('administer green_entity');
    $entity_type = $entity_type->reveal();

    $entity = $this->prophesize(ConfigEntityInterface::class);
    $entity->getEntityType()->willReturn($entity_type);
    $entity->getEntityTypeId()->willReturn('green_entity_bundle');
    $entity->id()->willReturn('default');
    $entity->uuid()->willReturn('fake uuid');
    $entity->language()->willReturn(new Language(['id' => LanguageInterface::LANGCODE_NOT_SPECIFIED]));

    // User with no access.
    $user = $this->buildMockUser(1, 'access content');
    $data[] = [$entity->reveal(), 'view label', $user->reveal(), FALSE];

    // Permissions which grant "view label" access.
    $permissions = [
      'administer green_entity',
      'view green_entity',
      'view default green_entity',
      'view own green_entity',
      'view any green_entity',
      'view own default green_entity',
      'view any default green_entity',
    ];
    foreach ($permissions as $index => $permission) {
      $user = $this->buildMockUser(10 + $index, $permission);
      $data[] = [$entity->reveal(), 'view label', $user->reveal(), TRUE];
    }

    return $data;
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
