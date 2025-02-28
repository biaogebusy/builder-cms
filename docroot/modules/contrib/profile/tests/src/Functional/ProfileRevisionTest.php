<?php

namespace Drupal\Tests\profile\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\profile\Entity\ProfileType;

/**
 * Tests using revisions with profiles.
 *
 * @group profile
 */
class ProfileRevisionTest extends ProfileTestBase {

  /**
   * Testing profile type that uses revisions.
   *
   * @var \Drupal\profile\Entity\ProfileType
   */
  protected $useRevisionsType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $use_revisions_type = ProfileType::create([
      'id' => $this->randomMachineName(),
      'label' => $this->randomMachineName(),
      'allow_revisions' => TRUE,
      'new_revision' => TRUE,
    ]);
    $use_revisions_type->save();
    $this->useRevisionsType = $use_revisions_type;

    $use_revisions_fullname_field = FieldConfig::create([
      'field_storage' => $this->field->getFieldStorageDefinition(),
      'bundle' => $this->useRevisionsType->id(),
      'label' => 'Full name',
    ]);
    $use_revisions_fullname_field->save();

    // Configure the default display.
    $use_revisions_display = EntityViewDisplay::load("profile.{$this->useRevisionsType->id()}.default");
    if (!$use_revisions_display) {
      $use_revisions_display = EntityViewDisplay::create([
        'targetEntityType' => 'profile',
        'bundle' => $this->useRevisionsType->id(),
        'mode' => 'default',
        'status' => TRUE,
      ]);
      $use_revisions_display->save();
    }
    $use_revisions_display
      ->setComponent($this->field->getName(), ['type' => 'string'])
      ->save();

    // Configure the default form.
    $use_revisions_form = EntityFormDisplay::load("profile.{$this->useRevisionsType->id()}.default");
    if (!$use_revisions_form) {
      $use_revisions_form = EntityFormDisplay::create([
        'targetEntityType' => 'profile',
        'bundle' => $this->useRevisionsType->id(),
        'mode' => 'default',
        'status' => TRUE,
      ]);
      $this->form->save();
    }
    $use_revisions_form
      ->setComponent($this->field->getName(), [
        'type' => 'string_textfield',
      ])->save();

  }

  /**
   * Tests revision handling.
   */
  public function testProfileRevisions() {
    /** @var \Drupal\profile\ProfileStorageInterface $storage */
    $storage = $this->container->get('entity_type.manager')->getStorage('profile');

    $user = $this->createUser([
      "create {$this->type->id()} profile",
      "view own {$this->type->id()} profile",
      "update own {$this->type->id()} profile",
    ]);
    $this->drupalLogin($user);

    $create_url = Url::fromRoute('profile.user_page.single', [
      'user' => $this->loggedInUser->id(),
      'profile_type' => $this->type->id(),
    ]);
    $this->drupalGet($create_url);
    $edit = [
      'profile_fullname[0][value]' => $this->getRandomGenerator()->word(10),
    ];
    $this->submitForm($edit, 'Save');

    $profile = $storage->loadByUser($this->loggedInUser, $this->type->id());
    $existing_profile_id = $profile->id();
    $existing_revision_id = $profile->getRevisionId();

    $create_url = Url::fromRoute('profile.user_page.single', [
      'user' => $this->loggedInUser->id(),
      'profile_type' => $this->type->id(),
    ]);
    $this->drupalGet($create_url);
    $edit = [
      'profile_fullname[0][value]' => $this->getRandomGenerator()->word(10),
    ];
    $this->submitForm($edit, 'Save');

    $storage->resetCache([$profile->id()]);
    $profile = $storage->loadByUser($this->loggedInUser, $this->type->id());
    $this->assertEquals($existing_profile_id, $profile->id());
    $this->assertEquals($existing_revision_id, $profile->getRevisionId());

    $user = $this->createUser([
      "create {$this->useRevisionsType->id()} profile",
      "view own {$this->useRevisionsType->id()} profile",
      "update own {$this->useRevisionsType->id()} profile",
    ]);
    $this->drupalLogin($user);

    $create_url = Url::fromRoute('profile.user_page.single', [
      'user' => $this->loggedInUser->id(),
      'profile_type' => $this->useRevisionsType->id(),
    ]);
    $this->drupalGet($create_url);
    $this->getSession()->getPage()->hasField('revision_log_message');
    $edit = [
      'profile_fullname[0][value]' => $this->getRandomGenerator()->word(10),
    ];
    $this->submitForm($edit, 'Save');

    $profile = $storage->loadByUser($this->loggedInUser, $this->useRevisionsType->id());
    $existing_profile_id = $profile->id();
    $existing_revision_id = $profile->getRevisionId();

    $create_url = Url::fromRoute('profile.user_page.single', [
      'user' => $this->loggedInUser->id(),
      'profile_type' => $this->useRevisionsType->id(),
    ]);
    $this->drupalGet($create_url);
    $edit = [
      'profile_fullname[0][value]' => $this->getRandomGenerator()->word(10),
    ];
    $this->submitForm($edit, 'Save');

    $storage->resetCache([$profile->id()]);
    $profile = $storage->loadByUser($this->loggedInUser, $this->useRevisionsType->id());
    $this->assertEquals($existing_profile_id, $profile->id());
    $this->assertNotEquals($existing_revision_id, $profile->getRevisionId());

    // Assert that unchecking the revision checkbox doesn't create a new
    // revision.
    $existing_revision_id = $profile->getRevisionId();
    $this->drupalGet($create_url);
    $this->getSession()->getPage()->uncheckField('revision');
    $edit = [
      'profile_fullname[0][value]' => $this->getRandomGenerator()->word(10),
    ];
    $this->submitForm($edit, 'Save');
    $storage->resetCache([$profile->id()]);
    $profile = $storage->loadByUser($this->loggedInUser, $this->useRevisionsType->id());
    $this->assertEquals($existing_profile_id, $profile->id());
    $this->assertEquals($existing_revision_id, $profile->getRevisionId());
  }

}
