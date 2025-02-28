<?php

namespace Drupal\Tests\profile\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\profile\Entity\ProfileType;
use Drupal\profile\ProfileTestTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests profile access handling.
 */
abstract class ProfileTestBase extends BrowserTestBase {

  use ProfileTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['profile', 'field_ui', 'text', 'block'];

  /**
   * Testing profile type entity.
   *
   * @var \Drupal\profile\Entity\ProfileType
   */
  protected $type;

  /**
   * Testing profile type entity view display.
   *
   * @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   */
  protected $display;

  /**
   * Testing profile type entity form display.
   *
   * @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface
   */
  protected $form;

  /**
   * Testing field on profile type.
   *
   * @var \Drupal\Core\Field\FieldConfigInterface
   */
  protected $field;

  /**
   * Testing admin user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('local_actions_block');
    $this->drupalPlaceBlock('page_title_block');

    $user_form_display = EntityFormDisplay::load("user.user.default");
    if (!$user_form_display) {
      $user_form_display = EntityFormDisplay::create([
        'targetEntityType' => 'user',
        'bundle' => 'user',
        'mode' => 'default',
        'status' => TRUE,
      ]);
      $user_form_display->save();
    }

    $this->type = $this->createProfileType('test', 'Test profile', TRUE);

    $id = $this->type->id();

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'profile_fullname',
      'entity_type' => 'profile',
      'type' => 'text',
    ]);
    $field_storage->save();

    $this->field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $this->type->id(),
      'label' => 'Full name',
    ]);
    $this->field->save();

    // Configure the default display.
    $user_display = EntityViewDisplay::load("user.user.default");
    if (!$user_display) {
      $user_display = EntityViewDisplay::create([
        'targetEntityType' => 'user',
        'bundle' => 'user',
        'mode' => 'default',
        'status' => TRUE,
      ]);
      $user_display->save();
    }

    // Configure the default display.
    $this->display = EntityViewDisplay::load("profile.{$this->type->id()}.default");
    if (!$this->display) {
      $this->display = EntityViewDisplay::create([
        'targetEntityType' => 'profile',
        'bundle' => $this->type->id(),
        'mode' => 'default',
        'status' => TRUE,
      ]);
      $this->display->save();
    }
    $this->display
      ->setComponent($this->field->getName(), ['type' => 'string'])
      ->save();

    // Configure the profile field display on user view modes.
    $profile_types = ProfileType::loadMultiple();
    foreach ($profile_types as $profile_type) {
      $field_name = $profile_type->id() . '_profiles';
      // Assign display properties for the 'default' view mode.
      $user_display->setComponent($field_name, [
        'label' => 'above',
        'type' => 'entity_reference_entity_view',
        'settings' => [
          'view_mode' => $this->display->id(),
        ],
      ])->save();
    }

    // Configure the default form.
    $this->form = EntityFormDisplay::load("profile.{$this->type->id()}.default");
    if (!$this->form) {
      $this->form = EntityFormDisplay::create([
        'targetEntityType' => 'profile',
        'bundle' => $this->type->id(),
        'mode' => 'default',
        'status' => TRUE,
      ]);
      $this->form->save();
    }
    $this->form
      ->setComponent($this->field->getName(), [
        'type' => 'string_textfield',
      ])->save();

    $this->checkPermissions([
      'administer profile types',
      "create $id profile",
      "view own $id profile",
      "view any $id profile",
      "update own $id profile",
      "update any $id profile",
      "delete own $id profile",
      "delete any $id profile",
    ]);

    $this->adminUser = $this->drupalCreateUser([
      'administer profile types',
      'administer profile',
      "view any $id profile",
      "create $id profile",
      "update any $id profile",
      "delete any $id profile",
    ]);
  }

}
