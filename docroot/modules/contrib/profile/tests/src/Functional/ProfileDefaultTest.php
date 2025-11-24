<?php

namespace Drupal\Tests\profile\Functional;

use Drupal\Core\Url;
use Drupal\profile\Entity\Profile;

/**
 * Tests "default" functionality via the UI.
 *
 * @group profile
 */
class ProfileDefaultTest extends ProfileTestBase {

  /**
   * Profile entity storage.
   *
   * @var \Drupal\profile\ProfileStorageInterface
   */
  public $profileStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'access user profiles',
      'administer profile',
      'administer profile types',
      'access administration pages',
    ]);
  }

  /**
   * Tests the entity.profile.set_default route.
   */
  public function testSetDefaultRoute() {
    $this->type->setMultiple(TRUE)->save();
    $id = $this->type->id();
    $test_user = $this->drupalCreateUser([
      "view own $id profile",
      "create $id profile",
      "update own $id profile",
      "delete own $id profile",
    ]);

    $profile1 = Profile::create([
      'type' => $id,
      'uid' => $test_user->id(),
      'profile_fullname' => 'Frederick Pabst',
    ]);
    $profile1->save();
    $profile2 = Profile::create([
      'type' => $id,
      'uid' => $test_user->id(),
      'profile_fullname' => 'Frederick Miller',
    ]);
    $profile2->save();

    $this->drupalLogin($test_user);
    $this->drupalGet(Url::fromRoute('profile.user_page.multiple', [
      'user' => $test_user->id(),
      'profile_type' => $id,
    ]));
    $this->assertSession()->pageTextContains('Frederick Pabst');
    $this->assertSession()->pageTextContains('Frederick Miller');
    $this->assertSession()->linkExists('Mark as default');
    $this->getSession()->getPage()->clickLink('Mark as default');
    $this->assertSession()->responseContains((string) t('The %label profile has been marked as default.', ['%label' => $profile2->label()]));
  }

  /**
   * Test profile display options on user entity display mode.
   */
  public function testProfileFieldOnUserDisplayConfig() {
    $id = $this->type->id();
    $this->drupalLogin($this->rootUser);

    // Check that profile field is configurable on user display mode.
    $this->drupalGet('admin/config/people/accounts/display');
    $field_label = $this->type->label() . ' profiles';
    $this->assertSession()->pageTextContains($field_label);
    $edit = ["fields[{$id}_profiles][label]" => 'inline'];
    $edit = ["fields[{$id}_profiles][type]" => 'entity_reference_entity_view'];
    $this->submitForm($edit, 'Save');
  }

}
