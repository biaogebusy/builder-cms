<?php

namespace Drupal\Tests\lightning_roles\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;

/**
 * @group lightning
 * @group lightning_roles
 * @group orca_public
 *
 * @requires module quickedit
 */
class ContentRoleManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'contextual',
    'lightning_roles',
    'node',
    'path',
    'quickedit',
    'system',
    'toolbar',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('lightning_roles');
    $this->installEntitySchema('user');
  }

  /**
   * Tests reacting to the creation and deletion of node types.
   *
   * @covers \Drupal\lightning_roles\ContentRoleManager::grantPermissions
   */
  public function test() {
    // Don't bother with ContentTypeCreationTrait, since it will try to create
    // things like the 'body' field, which is irrelevant in this test.
    $node_type = NodeType::create(['type' => 'page']);
    $node_type->save();

    // Give page creators the keys to the kingdom...
    $this->container->get('lightning.content_roles')
      ->grantPermissions('creator', ['bypass node access']);

    // Ensure the 'creator' role has all the expected OOTB permissions, plus
    // the one we just granted them.
    /** @var \Drupal\user\RoleInterface $role */
    $role = Role::load('page_creator');

    $expected_creator_permissions = [
      'create page content',
      'edit own page content',
      'view page revisions',
      'view own unpublished content',
      'create url aliases',
      'access in-place editing',
      'access contextual links',
      'access toolbar',
      'bypass node access',
    ];
    foreach ($expected_creator_permissions as $permission) {
      $this->assertContains($permission, $role->getPermissions());
    }
    // Content roles should never be administrators.
    $this->assertFalse($role->isAdmin());

    // Ensure the 'reviewer' role has all the expected OOTB permissions.
    $role = Role::load('page_reviewer');

    $expected_reviewer_permissions = [
      'access content overview',
      'edit any page content',
      'delete any page content',
    ];
    foreach ($expected_reviewer_permissions as $permission) {
      $this->assertTrue($role->hasPermission($permission));
    }
    $this->assertFalse($role->isAdmin());

    // After deleting the content type, both roles should be gone too.
    $node_type->delete();

    $roles = Role::loadMultiple([
      'page_creator',
      'page_reviewer',
    ]);
    $this->assertEmpty($roles);
  }

}
