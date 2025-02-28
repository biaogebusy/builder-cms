<?php

namespace Drupal\Tests\profile\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests profile field access functionality.
 *
 * @group profile
 */
class ProfileFieldAccessTest extends ProfileTestBase {

  /**
   * A test user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $webUser;

  /**
   * A test user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $otherUser;

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

    $user_permissions = [
      'access user profiles',
      "create {$this->type->id()} profile",
      "update own {$this->type->id()} profile",
      "view any {$this->type->id()} profile",
    ];

    $this->webUser   = $this->drupalCreateUser($user_permissions);
    $this->otherUser = $this->drupalCreateUser($user_permissions);
  }

  /**
   * Tests private profile field access.
   */
  public function testPrivateField() {
    $this->field->setThirdPartySetting('profile', 'profile_private', TRUE);
    $this->field->save();

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'profile_bio',
      'entity_type' => 'profile',
      'type' => 'text',
    ]);
    $field_storage->save();

    $bio_field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $this->type->id(),
      'label' => 'Bio',
    ]);
    $bio_field->save();

    $this->display
      ->setComponent($bio_field->getName(), [
        'type' => 'string',
        'label' => 'above',
      ])
      ->save();
    $this->form
      ->setComponent($bio_field->getName(), [
        'type' => 'text_default',
        'settings' => [],
      ])->save();

    // Fill in a field value.
    $this->drupalLogin($this->webUser);
    $secret = $this->randomMachineName();
    $not_secret = $this->randomMachineName();
    $this->drupalGet("user/{$this->webUser->id()}/{$this->type->id()}");
    $edit = [
      'profile_fullname[0][value]' => $secret,
      'profile_bio[0][value]' => $not_secret,
    ];
    $this->assertSession()->buttonNotExists('Save and make default');
    $this->submitForm($edit, 'Save');

    /** @var \Drupal\profile\ProfileStorageInterface $storage */
    $storage = $this->container->get('entity_type.manager')->getStorage('profile');
    $web_user_profile = $storage->loadByUser($this->webUser, $this->type->id());

    // Verify that the private field value appears for the profile owner.
    $this->drupalGet($web_user_profile->toUrl());
    $this->assertSession()->pageTextContains($secret);
    $this->assertSession()->pageTextContains($not_secret);

    // Verify that the private field value does not appear for other users.
    $this->drupalLogin($this->otherUser);
    $this->drupalGet($web_user_profile->toUrl());
    $this->assertSession()->pageTextNotContains($secret);
    $this->assertSession()->pageTextContains($not_secret);

    // Verify that the private field value appears for the administrator.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet($web_user_profile->toUrl());
    $this->assertSession()->pageTextContains($secret);
    $this->assertSession()->pageTextContains($not_secret);
  }

}
