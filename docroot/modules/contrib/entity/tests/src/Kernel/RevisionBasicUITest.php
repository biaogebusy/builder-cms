<?php

namespace Drupal\Tests\entity\Kernel;

use Drupal\entity_module_test\Entity\EnhancedEntity;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;

/**
 * @group entity
 */
class RevisionBasicUITest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_module_test', 'system', 'user', 'entity'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test_enhanced');
    $this->installSchema('system', 'sequences');
    $this->installConfig(['system', 'user']);

    $this->container->get('router.builder')->rebuild();

    // Create a test user so that the mock requests performed below have a valid
    // current user context.
    $user = User::create([
      // Make sure not to create user 1 which would bypass any access
      // restrictions.
      'uid' => 2,
      'name' => 'Test user',
    ]);
    $user->save();
    $this->container->get('account_switcher')->switchTo($user);
  }

  /**
   * Tests the revision history controller.
   */
  public function testRevisionHistory() {
    $entity = EnhancedEntity::create([
      'name' => 'rev 1',
      'type' => 'default',
    ]);
    $entity->save();

    $revision = clone $entity;
    $revision->name->value = 'rev 2';
    $revision->setNewRevision(TRUE);
    $revision->isDefaultRevision(FALSE);
    $revision->save();

    /** @var \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel */
    $http_kernel = $this->container->get('http_kernel');
    $request = Request::create($revision->toUrl('version-history')->toString());
    $response = $http_kernel->handle($request);
    $this->assertEquals(403, $response->getStatusCode());

    $role_admin = Role::create([
      'id' => 'test_role_admin',
      'label' => 'Test role admin',
    ]);
    $role_admin->grantPermission('administer entity_test_enhanced');
    $role_admin->save();

    $role = Role::create([
      'id' => 'test_role',
      'label' => 'Test role',
    ]);
    $role->grantPermission('view all entity_test_enhanced revisions');
    $role->grantPermission('administer entity_test_enhanced');
    $role->save();

    $user_admin = User::create([
      'name' => 'Test administrator',
    ]);
    $user_admin->addRole($role_admin->id());
    $user_admin->save();
    $this->container->get('account_switcher')->switchTo($user_admin);

    $request = Request::create($revision->toUrl('version-history')->toString());
    $response = $http_kernel->handle($request);
    $this->assertEquals(200, $response->getStatusCode());

    $user = User::create([
      'name' => 'Test editor',
    ]);
    $user->addRole($role->id());
    $user->save();
    $this->container->get('account_switcher')->switchTo($user);

    $request = Request::create($revision->toUrl('version-history')->toString());
    $response = $http_kernel->handle($request);
    $this->assertEquals(200, $response->getStatusCode());

    // This ensures that the default revision is still the first revision.
    $this->assertTrue(strpos($response->getContent(), 'entity_test_enhanced/1/revisions/2/view') !== FALSE);
    $this->assertTrue(strpos($response->getContent(), 'entity_test_enhanced/1') !== FALSE);

    // Publish a new revision.
    $revision = clone $entity;
    $revision->name->value = 'rev 3';
    $revision->setNewRevision(TRUE);
    $revision->isDefaultRevision(TRUE);
    $revision->save();

    $request = Request::create($revision->toUrl('version-history')->toString());
    $response = $http_kernel->handle($request);
    $this->assertEquals(200, $response->getStatusCode());

    // The first revision row should now include a revert link.
    $this->assertTrue(strpos($response->getContent(), 'entity_test_enhanced/1/revisions/1/revert') !== FALSE);
  }

  public function testRevisionView() {
    $entity = EnhancedEntity::create([
      'name' => 'rev 1',
      'type' => 'default',
    ]);
    $entity->save();

    $revision = clone $entity;
    $revision->name->value = 'rev 2';
    $revision->setNewRevision(TRUE);
    $revision->isDefaultRevision(FALSE);
    $revision->save();

    /** @var \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel */
    $http_kernel = $this->container->get('http_kernel');
    $request = Request::create($revision->toUrl('revision')->toString());
    $response = $http_kernel->handle($request);
    $this->assertEquals(403, $response->getStatusCode());

    $role_admin = Role::create([
      'id' => 'test_role_admin',
      'label' => 'Test role admin',
    ]);
    $role_admin->grantPermission('administer entity_test_enhanced');
    $role_admin->save();

    $role = Role::create([
      'id' => 'test_role',
      'label' => 'Test role',
    ]);
    $role->grantPermission('view all entity_test_enhanced revisions');
    $role->grantPermission('administer entity_test_enhanced');
    $role->save();

    $user_admin = User::create([
      'name' => 'Test administrator',
    ]);
    $user_admin->addRole($role_admin->id());
    $user_admin->save();
    $this->container->get('account_switcher')->switchTo($user_admin);

    $request = Request::create($revision->toUrl('version-history')->toString());
    $response = $http_kernel->handle($request);
    $this->assertEquals(200, $response->getStatusCode());

    $user = User::create([
      'name' => 'Test editor',
    ]);
    $user->addRole($role->id());
    $user->save();
    $this->container->get('account_switcher')->switchTo($user);

    $request = Request::create($revision->toUrl('revision')->toString());
    $response = $http_kernel->handle($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertStringNotContainsString('rev 1', $response->getContent());
    $this->assertStringContainsString('rev 2', $response->getContent());
  }

  public function testRevisionRevert() {
    $entity = EnhancedEntity::create([
      'name' => 'rev 1',
      'type' => 'entity_test_enhance',
    ]);
    $entity->save();
    $entity->name->value = 'rev 2';
    $entity->setNewRevision(TRUE);
    $entity->isDefaultRevision(TRUE);
    $entity->save();

    $role = Role::create([
      'id' => 'test_role',
      'label' => 'Test role',
    ]);
    $role->grantPermission('administer entity_test_enhanced');
    $role->grantPermission('revert all entity_test_enhanced revisions');
    $role->save();

    $user = User::create([
      'name' => 'Test administrator',
    ]);
    $user->addRole($role->id());
    $user->save();
    $this->container->get('account_switcher')->switchTo($user);

    /** @var \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel */
    $http_kernel = $this->container->get('http_kernel');
    $request = Request::create($entity->toUrl('revision-revert-form')->toString());
    $response = $http_kernel->handle($request);
    $this->assertEquals(200, $response->getStatusCode());
  }

}
