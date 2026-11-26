<?php

namespace Drupal\Tests\xinshi_api\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\RoleInterface;
use Drupal\xinshi_api\Controller\RoleController;
use PHPUnit\Framework\TestCase;

/** Checks selectable role data and configuration cache invalidation. */
final class RoleControllerTest extends TestCase {

  public function testListUsesMachineNamesAndTranslatedLabelsWithoutPermissions(): void {
    $roles = [];
    foreach (['anonymous' => 'Anonymous', 'authenticated' => '已登录', 'webmaster' => '编辑员'] as $id => $label) {
      $role = $this->createMock(RoleInterface::class);
      $role->method('id')->willReturn($id);
      $role->method('label')->willReturn($label);
      $role->method('getCacheTags')->willReturn(['config:user.role.' . $id]);
      $role->expects($this->never())->method('getPermissions');
      $roles[$id] = $role;
    }
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())->method('loadMultiple')->willReturn($roles);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('user_role')->willReturn($storage);
    $settings = $this->createMock(ImmutableConfig::class);
    $settings->method('get')->with('debug')->willReturn(FALSE);
    $config = $this->createMock(ConfigFactoryInterface::class);
    $config->method('get')->with('xinshi_api.settings')->willReturn($settings);
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $manager);
    $container->set('config.factory', $config);
    \Drupal::setContainer($container);
    try {
      $response = (new RoleController())->roleList();
      $this->assertSame([
        ['id' => 'authenticated', 'label' => '已登录'],
        ['id' => 'webmaster', 'label' => '编辑员'],
      ], json_decode($response->getContent(), TRUE, 512, JSON_THROW_ON_ERROR));
      $metadata = $response->getCacheableMetadata();
      $this->assertEqualsCanonicalizing([
        'config:user_role_list', 'config:user.role.authenticated', 'config:user.role.webmaster',
      ], $metadata->getCacheTags());
      $this->assertEqualsCanonicalizing([
        'url', 'user.permissions', 'languages:language_interface',
      ], $metadata->getCacheContexts());
    }
    finally {
      \Drupal::unsetContainer();
    }
  }

}
