<?php

namespace Drupal\Tests\profile\Kernel\Migrate\d7;

use Drupal\profile\Entity\ProfileType;
use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;
use Drupal\user\Entity\User;

/**
 * Tests migration of Profile2 types and profiles.
 *
 * @group profile
 */
class Profile2MigrateTest extends MigrateDrupal7TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'comment',
    'datetime',
    'datetime_range',
    'image',
    'language',
    'link',
    'menu_ui',
    'node',
    'profile',
    'taxonomy',
    'telephone',
    'text',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('profile');
    $this->installEntitySchema('view');
    $this->installConfig(['profile', 'user']);
    $profile2Fixture = __DIR__ . '/../../../../fixtures/drupal7.php';
    $this->assertNotFalse(realpath($profile2Fixture));
    $this->loadFixture($profile2Fixture);

    // Migrate profile types.
    $this->migrateProfile2Types();

    // Migrate profiles.
    $this->migrateProfile2Profiles();
  }

  /**
   * Tests profile2 type migration.
   */
  public function testProfile2TypeMigration() {
    $profile_type = ProfileType::load('main');
    $this->assertNotNull($profile_type);
    $this->assertEquals('Main profile', $profile_type->label());
    $this->assertTrue($profile_type->getRegistration());
    $this->assertTrue(in_array('authenticated', $profile_type->getRoles()));
    $this->assertTrue(in_array('administrator', $profile_type->getRoles()));

    $profile_type = ProfileType::load('test_profile_type');
    $this->assertNotNull($profile_type);
    $this->assertEquals('Test profile type', $profile_type->label());
    $this->assertFalse($profile_type->getRegistration());
    $this->assertFalse(in_array('authenticated', $profile_type->getRoles()));
    $this->assertTrue(in_array('administrator', $profile_type->getRoles()));
  }

  /**
   * Tests profile2 profiles migration.
   */
  public function testProfile2ProfilesMigration() {
    // Get main profiles for users Odo (2) and Bob (3).
    $odo_main = \Drupal::entityTypeManager()
      ->getStorage('profile')
      ->loadByUser(User::load(2), 'main');
    $bob_main = \Drupal::entityTypeManager()
      ->getStorage('profile')
      ->loadByUser(User::load(3), 'main');

    // Make sure the profiles and field exist and have the expected values.
    $this->assertNotNull($odo_main);
    $this->assertNotNull($bob_main);
    $this->assertTrue($odo_main->hasField('field_main_profile_email_field'));
    $this->assertEquals('odo@odo.odo', $odo_main->get('field_main_profile_email_field')->getString());
    $this->assertEquals('bob@bob.bob', $bob_main->get('field_main_profile_email_field')->getString());

    // Get test profiles for users Odo (2) and Bob (3).
    $odo_test = \Drupal::entityTypeManager()
      ->getStorage('profile')
      ->loadByUser(User::load(2), 'test_profile_type');
    $bob_test = \Drupal::entityTypeManager()
      ->getStorage('profile')
      ->loadByUser(User::load(3), 'test_profile_type');

    // Make sure profiles and field exist and have the expected values.
    $this->assertNotNull($odo_test);
    $this->assertNotNull($bob_test);
    $this->assertTrue($odo_test->hasField('field_test_profile_phone'));
    $this->assertEquals('(555) 555-1234', $odo_test->get('field_test_profile_phone')->getString());
    $this->assertEquals('(250) 555-0199', $bob_test->get('field_test_profile_phone')->getString());
  }

  /**
   * Helper function to migrate profile2 types.
   */
  protected function migrateProfile2Types() {
    $this->installConfig(['profile', 'user']);
    $this->installEntitySchema('profile');
    $this->migrateUsers(FALSE);
    $this->executeMigration('d7_profile2_type');
    $this->migrateFields();
  }

  /**
   * Helper function to migrate profile2 profiles.
   */
  protected function migrateProfile2Profiles() {
    $this->executeMigrations(['d7_profile2:main', 'd7_profile2:test_profile_type']);
  }

}
