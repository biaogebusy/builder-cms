<?php

namespace Drupal\Tests\profile\Functional;

use Drupal\Core\Url;
use Drupal\profile\Entity\Profile;
use Drupal\user\Entity\User;

/**
 * Tests the user pages.
 *
 * @group profile
 */
class UserTest extends ProfileTestBase {

  /**
   * Tests the user pages with a "single" profile type.
   */
  public function testSingle() {
    /** @var \Drupal\profile\ProfileStorageInterface $profile_storage */
    $profile_storage = $this->container->get('entity_type.manager')->getStorage('profile');
    $first_user = $this->createUser(['view own test profile']);
    $second_user = $this->createUser([
      'create test profile',
      'update own test profile',
      'view own test profile',
    ]);

    // Confirm that the user with only "view" permissions can't see the page.
    $this->drupalLogin($first_user);
    $url = Url::fromRoute('profile.user_page.single', [
      'user' => $first_user->id(),
      'profile_type' => $this->type->id(),
    ]);
    $this->drupalGet($url);
    $this->assertSession()->pageTextContains('Access denied');

    // Confirm that the user with "update" permissions can see the page.
    $this->drupalLogin($second_user);
    $url = Url::fromRoute('profile.user_page.single', [
      'user' => $second_user->id(),
      'profile_type' => $this->type->id(),
    ]);
    $this->drupalGet($url);
    $this->assertSession()->fieldExists('profile_fullname[0][value]');
    $this->assertSession()->titleEquals($this->type->getDisplayLabel() . ' | Drupal');

    // Confirm that a profile can be created.
    $this->submitForm([
      'profile_fullname[0][value]' => 'John Smith',
    ], 'Save');
    $this->assertSession()->pageTextContains('The profile has been saved.');
    $profile = $profile_storage->loadByUser($second_user, 'test');
    $this->assertNotEmpty($profile);
    $this->assertEquals('John Smith', $profile->get('profile_fullname')->value);

    // Confirm that the created profile can be edited.
    $this->drupalGet($url);
    $this->assertSession()->titleEquals($this->type->getDisplayLabel() . ' | Drupal');
    $this->submitForm([
      'profile_fullname[0][value]' => 'John Smith Jr.',
    ], 'Save');
    $this->assertSession()->pageTextContains('The profile has been saved.');
    $profile_storage->resetCache([$profile->id()]);
    $updated_profile = $profile_storage->loadByUser($second_user, 'test');
    $this->assertNotEmpty($updated_profile);
    $this->assertEquals($profile->id(), $updated_profile->id());
    $this->assertEquals('John Smith Jr.', $updated_profile->get('profile_fullname')->value);

    // Confirm that the "multiple" routes are unavailable.
    $routes = [
      'profile.user_page.multiple',
      'profile.user_page.add_form',
    ];
    foreach ($routes as $route_name) {
      $url = Url::fromRoute($route_name, [
        'user' => $second_user->id(),
        'profile_type' => $this->type->id(),
      ]);
      $this->drupalGet($url);
      $this->assertSession()->pageTextContains('Access denied');
    }
  }

  /**
   * Tests the user pages with a "multiple" profile type.
   */
  public function testMultiple() {
    $this->type->setMultiple(TRUE);
    $this->type->save();
    /** @var \Drupal\profile\ProfileStorageInterface $profile_storage */
    $profile_storage = $this->container->get('entity_type.manager')->getStorage('profile');
    $user = $this->createUser([
      'create test profile',
      'update own test profile',
      'view own test profile',
    ]);

    $this->drupalLogin($user);
    $overview_url = Url::fromRoute('profile.user_page.multiple', [
      'user' => $user->id(),
      'profile_type' => $this->type->id(),
    ]);
    $this->drupalGet($overview_url);
    $this->assertSession()->titleEquals($this->type->getDisplayLabel() . ' | Drupal');
    $this->assertSession()->pageTextContains('Add profile');

    // Confirm that a new profile can be added.
    $this->getSession()->getPage()->clickLink('Add profile');
    $this->assertSession()->titleEquals('Add profile | Drupal');
    $this->submitForm([
      'profile_fullname[0][value]' => 'John Smith',
    ], 'Save');
    $this->assertSession()->pageTextContains('Test profile #1 has been saved.');
    $profile = $profile_storage->load('1');
    $this->assertNotEmpty($profile);
    $this->assertEquals('John Smith', $profile->get('profile_fullname')->value);

    // Confirm that the new profile is listed.
    $this->assertSession()->pageTextContains('John Smith');

    // Confirm that the profile can be edited.
    $profile_edit_form = $profile->toUrl('edit-form');
    $this->assertSession()->linkByHrefExists($profile_edit_form->toString());
    $this->drupalGet($profile_edit_form);
    $this->assertSession()->titleEquals("Edit {$this->type->label()} #{$profile->id()} | Drupal");
    $this->submitForm([
      'profile_fullname[0][value]' => 'John Smith Jr.',
    ], 'Save');
    $this->assertSession()->pageTextContains('Test profile #1 has been saved.');
    $profile_storage->resetCache([$profile->id()]);
    $profile = $profile_storage->load($profile->id());
    $this->assertNotEmpty($profile);
    $this->assertEquals('John Smith Jr.', $profile->get('profile_fullname')->value);

    // Confirm that the "single" route is unavailable.
    $url = Url::fromRoute('profile.user_page.single', [
      'user' => $user->id(),
      'profile_type' => $this->type->id(),
    ]);
    $this->drupalGet($url);
    $this->assertSession()->pageTextContains('Access denied');
  }

  /**
   * Tests access to the profile "add" page.
   */
  public function testAddPageAccess() {
    $this->type->setMultiple(TRUE);
    $this->type->save();

    $first_user = $this->createUser(['view own test profile']);
    $second_user = $this->createUser([
      'create test profile',
      'view any profile',
      'access user profiles',
    ]);
    $third_user = $this->createUser([
      'administer profile',
      'access user profiles',
    ]);

    $profile = Profile::create([
      'type' => $this->type->id(),
      'uid' => $first_user->id(),
      'profile_fullname' => 'John Smith',
      'status' => TRUE,
    ]);
    $profile->save();

    // Confirm that the user with only "view" permissions can see
    // the overview page, but not the "add" page.
    $this->drupalLogin($first_user);
    $overview_url = Url::fromRoute('profile.user_page.multiple', [
      'user' => $first_user->id(),
      'profile_type' => $this->type->id(),
    ]);
    $this->drupalGet($overview_url);
    $this->assertSession()->pageTextContains('John Smith');
    $this->assertSession()->linkNotExists('Add profile');

    $add_url = Url::fromRoute('profile.user_page.add_form', [
      'user' => $first_user->id(),
      'profile_type' => $this->type->id(),
    ]);
    $this->drupalGet($add_url);
    $this->assertSession()->pageTextContains('Access denied');

    // Confirm that the second user can't add a profile for the first user.
    $this->drupalLogin($second_user);
    $this->drupalGet($overview_url);
    $this->assertSession()->pageTextContains('John Smith');
    $this->assertSession()->linkNotExists('Add profile');

    $add_url = Url::fromRoute('profile.user_page.add_form', [
      'user' => $first_user->id(),
      'profile_type' => $this->type->id(),
    ]);
    $this->drupalGet($add_url);
    $this->assertSession()->pageTextContains('Access denied');

    // Confirm that the third user can add a profile for the first user.
    $this->drupalLogin($third_user);
    $this->drupalGet($overview_url);
    $this->assertSession()->pageTextContains('John Smith');
    $this->assertSession()->linkExists('Add profile');
    $this->getSession()->getPage()->clickLink('Add profile');
    $this->submitForm([
      'profile_fullname[0][value]' => 'Jane Smith',
    ], 'Save');
    $this->assertSession()->pageTextContains('Test profile #2 has been saved.');
    $profile = Profile::load('2');
    $this->assertNotEmpty($profile);
    $this->assertEquals('Jane Smith', $profile->get('profile_fullname')->value);
    $this->assertEquals($first_user->id(), $profile->getOwnerId());
  }

  /**
   * Tests access when a user can't view another user's canonical page.
   */
  public function testIncompleteAccess() {
    $this->type->setMultiple(TRUE);
    $this->type->save();
    $first_user = $this->createUser(['view own test profile']);
    $second_user = $this->createUser(['administer profile']);

    $profile = Profile::create([
      'type' => $this->type->id(),
      'uid' => $first_user->id(),
      'profile_fullname' => 'John Smith',
      'status' => TRUE,
    ]);
    $profile->save();

    // Confirm that the first user can see their profile.
    $this->drupalLogin($first_user);
    $overview_url = Url::fromRoute('profile.user_page.multiple', [
      'user' => $first_user->id(),
      'profile_type' => $this->type->id(),
    ]);
    $this->drupalGet($overview_url);
    $this->assertSession()->pageTextContains('John Smith');

    // Confirm that the second user cannot see the first user's profile,
    // nor add a new one, because they don't have access to that user's
    // canonical page.
    $this->drupalLogin($second_user);
    $this->drupalGet($first_user->toUrl('canonical'));
    $this->assertSession()->pageTextContains('Access denied');
    $this->drupalGet($overview_url);
    $this->assertSession()->pageTextContains('Access denied');

    $add_url = Url::fromRoute('profile.user_page.add_form', [
      'user' => $first_user->id(),
      'profile_type' => $this->type->id(),
    ]);
    $this->drupalGet($add_url);
    $this->assertSession()->pageTextContains('Access denied');
  }

  /**
   * Tests that a profile belonging to an anonymous user can be edited.
   */
  public function testAnonymousEdit() {
    $profile = $this->createProfile($this->type, User::getAnonymousUser());

    $this->drupalLogin($this->adminUser);
    $this->drupalGet($profile->toUrl('edit-form'));
    $profile_fullname = $this->randomString();
    $edit = [
      'profile_fullname[0][value]' => $profile_fullname,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->addressEquals($profile->toUrl('collection'));
  }

}
