<?php

namespace Drupal\Tests\social_auth\Functional;

use Drupal\Core\Url;
use Drupal\Tests\social_api\Functional\SocialApiTestBase;

/**
 * Defines the class  for testing the social auth profiles list.
 *
 * @group social_auth
 */
class SocialAuthListTest extends SocialApiTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['views', 'social_auth'];

  /**
   * The social auth data handler.
   *
   * @var \Drupal\social_auth\SocialAuthDataHandler
   */
  protected $dataHandler;

  /**
   * The entity type manager object.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * A user without any particular permissions to be used in testing.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $user;

  /**
   * The social auth user authenticator.
   *
   * @var \Drupal\social_auth\User\UserAuthenticator
   */
  protected $userAuthenticator;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {

    $this->adminUserPermissions = ['administer social api authentication'];

    parent::setUp();
    $this->dataHandler = \Drupal::getContainer()->get('social_auth.data_handler');
    $this->entityTypeManager = \Drupal::entityTypeManager();
    $this->user = $this->drupalCreateUser();
    $this->userAuthenticator = \Drupal::getContainer()->get('social_auth.user_authenticator');
    // Create a new role, which implicitly checks if the permission exists.
    $ownDeleteRole = $this->createRole([
      'delete own social auth profile',
    ]);
    $this->user->addRole($ownDeleteRole);
    $this->user->save();
  }

  /**
   * Test if list exists for various kind of users.
   */
  public function testListAccess() {
    // Test for a non-authenticated user.
    $this->drupalGet(Url::fromRoute('social_auth.user.profiles', ['user' => $this->noPermsUser->id()]));
    $this->assertSession()->statusCodeEquals(403);

    // Test for list access with an authenticated user.
    $this->drupalLogin($this->noPermsUser);
    $this->drupalGet(Url::fromRoute('social_auth.user.profiles', ['user' => $this->noPermsUser->id()]));
    $this->assertSession()->pageTextContains('Social authentication profiles');
  }

  /**
   * Test if user could access other user social auth list.
   */
  public function testListEntries() {
    $this->drupalLogin($this->user);

    // Associates a provider.
    $this->userAuthenticator->setPluginId('social_auth_provider1');
    $this->dataHandler->setSessionPrefix('social_auth_provider1');
    $this->userAuthenticator->associateNewProvider('provider_id_123', 'token123');

    // Associates another provider.
    $this->userAuthenticator->setPluginId('social_auth_provider2');
    $this->dataHandler->setSessionPrefix('social_auth_provider2');
    $this->userAuthenticator->associateNewProvider('provider_id_123', 'token123');

    // Test if has two rows.
    $this->drupalGet(Url::fromRoute('social_auth.user.profiles', ['user' => $this->user->id()]));
    $this->assertSession()->pageTextContains('social_auth_provider1');
    $this->assertSession()->pageTextContains('social_auth_provider2');

    // Test noPermsUser cannot access user social auth list.
    $this->drupalLogin($this->noPermsUser);
    $this->drupalGet(Url::fromRoute('social_auth.user.profiles', ['user' => $this->user->id()]));
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Test removal of socialauth entity from user social auth list.
   */
  public function testListSocialAuthRemoval() {
    $this->drupalLogin($this->user);

    $uid = $this->user->id();

    // Associates a provider.
    $this->userAuthenticator->setPluginId('social_auth_provider1');
    $this->dataHandler->setSessionPrefix('social_auth_provider1');
    $this->userAuthenticator->associateNewProvider('provider_id_123', 'token123');

    // Associates another provider.
    $this->userAuthenticator->setPluginId('social_auth_provider2');
    $this->dataHandler->setSessionPrefix('social_auth_provider2');
    $this->userAuthenticator->associateNewProvider('provider_id_123', 'token123');

    // Test if has two rows.
    $this->drupalGet(Url::fromRoute('social_auth.user.profiles', ['user' => $uid]));
    $this->assertSession()->pageTextContains('social_auth_provider1');
    $this->assertSession()->pageTextContains('social_auth_provider2');

    try {
      $social_auth_storage = $this->entityTypeManager->getStorage('social_auth');

      $social_auth_users = $social_auth_storage->loadByProperties([
        'user_id' => $uid,
      ]);

      // Expects that the user has two associated providers.
      $this->assertEquals(2, count($social_auth_users), 'Number of associated providers should be 2');

      $this->clickLink("Delete");

      $this->assertSession()->pageTextContains('Are you sure you want to delete');
      $this->submitForm([], 'Delete');

      $social_auth_storage = $this->entityTypeManager->getStorage('social_auth');

      $social_auth_users = $social_auth_storage->loadByProperties([
        'user_id' => $uid,
      ]);

      // Expects that the user has one associated provider.
      $this->assertEquals(1, count($social_auth_users), 'Number of associated providers should be 1');
    }
    catch (\Exception $e) {
      $this->fail($e->getMessage());
    }
  }

  /**
   * Test removal of socialauth entity from other user.
   */
  public function testSocialAuthRemovalAccess() {
    $this->drupalLogin($this->user);

    // Associates a provider.
    $this->userAuthenticator->setPluginId('social_auth_provider1');
    $this->dataHandler->setSessionPrefix('social_auth_provider1');
    $this->userAuthenticator->associateNewProvider('provider_id_123', 'token123');

    // Associates another provider.
    $this->userAuthenticator->setPluginId('social_auth_provider2');
    $this->dataHandler->setSessionPrefix('social_auth_provider2');
    $this->userAuthenticator->associateNewProvider('provider_id_123', 'token123');

    // User with delete own social_auth can access social_auth delete route.
    $this->drupalGet(Url::fromRoute('entity.social_auth.delete_form', ['social_auth' => 1]));

    $this->assertSession()->pageTextContains('Are you sure you want to delete');

    // Test noPermsUser cannot delete other user social auth.
    $this->drupalLogin($this->noPermsUser);
    $this->drupalGet(Url::fromRoute('entity.social_auth.delete_form', ['social_auth' => 1]));
    $this->assertSession()->statusCodeEquals(403);

    // Associates a provider for noPermsUser.
    $this->userAuthenticator->setPluginId('social_auth_provider1');
    $this->dataHandler->setSessionPrefix('social_auth_provider1');
    $this->userAuthenticator->associateNewProvider('provider_id_123', 'token456');

    // Test noPermsUser cannot delete own user social auth (no role permission)
    $this->drupalGet(Url::fromRoute('entity.social_auth.delete_form', ['social_auth' => 3]));
    $this->assertSession()->statusCodeEquals(403);

  }

}
