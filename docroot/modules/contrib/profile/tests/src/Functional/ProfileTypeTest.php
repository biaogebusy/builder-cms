<?php

namespace Drupal\Tests\profile\Functional;

use Drupal\profile\Entity\Profile;
use Drupal\profile\Entity\ProfileType;

/**
 * Tests the profile type UI.
 *
 * @group profile
 */
class ProfileTypeTest extends ProfileTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'access user profiles',
      'administer profile',
      'administer profile types',
      'administer profile fields',
      'administer profile display',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests adding a profile type.
   */
  public function testAdd() {
    $this->drupalGet('admin/config/people/profile-types/add');
    $edit = [
      'id' => 'customer',
      'label' => 'Customer',
      'display_label' => 'Customer information',
      'registration' => TRUE,
      'allow_revisions' => TRUE,
      'new_revision' => TRUE,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('Saved the Customer profile type.');

    $profile_type = ProfileType::load($edit['id']);
    $this->assertNotEmpty($profile_type);
    $this->assertEquals('Customer', $profile_type->label());
    $this->assertEquals('Customer information', $profile_type->getDisplayLabel());
    $this->assertTrue($profile_type->getRegistration());
    $this->assertTrue($profile_type->allowsRevisions());
    $this->assertTrue($profile_type->shouldCreateNewRevision());
  }

  /**
   * Tests editing a profile type.
   */
  public function testEdit() {
    $profile_type = ProfileType::create([
      'id' => 'customer',
      'label' => 'Customer',
      'display_label' => 'Customer information',
      'registration' => FALSE,
      'allow_revisions' => TRUE,
      'new_revision' => TRUE,
    ]);
    $profile_type->save();

    $this->drupalGet($profile_type->toUrl('edit-form'));
    $edit = [
      'label' => 'Customer!',
      'display_label' => 'Customer information!',
      'registration' => TRUE,
      'allow_revisions' => FALSE,
      'new_revision' => TRUE,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('Saved the Customer! profile type.');

    $profile_type = ProfileType::load('customer');
    $this->assertEquals($edit['label'], $profile_type->label());
    $this->assertEquals($edit['display_label'], $profile_type->getDisplayLabel());
    $this->assertTrue($profile_type->getRegistration());
    $this->assertFalse($profile_type->allowsRevisions());
    $this->assertFalse($profile_type->shouldCreateNewRevision());
  }

  /**
   * Tests duplicating a profile type.
   */
  public function testDuplicate() {
    $profile_type = ProfileType::create([
      'id' => 'customer',
      'label' => 'Customer',
    ]);
    $profile_type->save();

    $this->drupalGet($profile_type->toUrl('duplicate-form'));
    $this->assertSession()->fieldValueEquals('label', 'Customer');
    $edit = [
      'label' => 'Customer2',
      'id' => 'customer2',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('Saved the Customer2 profile type.');

    // Confirm that the original profile type is unchanged.
    $profile_type = ProfileType::load('customer');
    $this->assertNotEmpty($profile_type);
    $this->assertEquals('Customer', $profile_type->label());

    // Confirm that the new profile type has the expected data.
    $profile_type = ProfileType::load('customer2');
    $this->assertNotEmpty($profile_type);
    $this->assertEquals('Customer2', $profile_type->label());
  }

  /**
   * Tests deleting a product type.
   */
  public function testDelete() {
    $profile_type = ProfileType::create([
      'id' => 'customer',
      'label' => 'Customer',
    ]);
    $profile_type->save();
    $profile = Profile::create([
      'type' => 'customer',
      'uid' => $this->adminUser->id(),
    ]);
    $profile->save();

    // Confirm that the type can't be deleted while there's a profile.
    $this->drupalGet($profile_type->toUrl('delete-form'));
    $this->assertSession()->pageTextContains(t('@type is used by 1 profile on your site. You cannot remove this profile type until you have removed all of the @type profiles.', ['@type' => $profile_type->label()]));
    $this->assertSession()->pageTextNotContains('This action cannot be undone.');

    // Delete the profile, confirm that deletion works.
    $profile->delete();
    $profile_type->save();
    $this->drupalGet($profile_type->toUrl('delete-form'));
    $this->assertSession()->pageTextContains(t('Are you sure you want to delete the profile type @type?', ['@type' => $profile_type->label()]));
    $this->assertSession()->pageTextContains('This action cannot be undone.');
    $this->submitForm([], 'Delete');
    $profile_type_exists = (bool) ProfileType::load($profile_type->id());
    $this->assertEmpty($profile_type_exists);
  }

}
