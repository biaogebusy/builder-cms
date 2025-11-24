<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms\Kernel\Migrate;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\migrate\MigrateExecutable;
use Drupal\sms\Entity\PhoneNumberSettings;
use Drupal\sms\Entity\PhoneNumberVerification;
use Drupal\sms\Plugin\Migrate\process\PhoneNumberSettings as PhoneNumberSettingsPlugin;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * This trait executes tests for D6 and D7 SMS Framework migrations.
 *
 * @see \Drupal\Tests\sms\Kernel\Migrate\MigrateD6SmsPhoneNumberTest
 * @see \Drupal\Tests\sms\Kernel\Migrate\MigrateD7SmsPhoneNumberTest
 */
trait MigratePhoneNumberTestTrait {

  /**
   * Tests migration of phone number settings based on legacy configuration.
   */
  public function testPhoneSettingsMigration(): void {
    $settings = PhoneNumberSettings::loadMultiple();
    static::assertEquals([], $settings);

    // Execute the phone number settings migration and confirm.
    $this->executeMigration('phone_number_settings');

    // Confirm new phone number settings is created.
    $settings = PhoneNumberSettings::loadMultiple();
    static::assertCount(1, $settings);
    /** @var \Drupal\sms\Entity\PhoneNumberSettingsInterface $setting */
    $setting = reset($settings);
    static::assertEquals(PhoneNumberSettingsPlugin::DEFAULT_VERIFICATION_MESSAGE, $setting->getVerificationMessage());
    static::assertEquals('phone_number', $setting->getFieldName('phone_number'));
    static::assertEquals(TRUE, $setting->getPurgeVerificationPhoneNumber());
    static::assertEquals('user', $setting->getPhoneNumberBundle());
    static::assertEquals('user', $setting->getPhoneNumberEntityTypeId());
    static::assertEquals(600, $setting->getVerificationCodeLifetime());

    // Confirm that a new phone number field is created.
    $field_storage = FieldStorageConfig::load('user.phone_number');
    static::assertEquals('user.phone_number', $field_storage->id());
    static::assertEquals('phone_number', $field_storage->getName());
    static::assertEquals('user', $field_storage->getTargetEntityTypeId());
    static::assertEquals('telephone', $field_storage->getType());

    $field_config = FieldConfig::load('user.user.phone_number');
    static::assertEquals('user', $field_config->getTargetEntityTypeId());
    static::assertEquals('user', $field_config->getTargetBundle());
  }

  /**
   * Tests phone number migration with custom phone number verification message.
   */
  public function testPhoneSettingsMigrationWithCustomVerificationMessage(): void {
    $this->loadFixture($this->confirmationMessageFixturePath());

    // Execute the phone number settings migration and confirm.
    $this->executeMigration('phone_number_settings');

    $settings = PhoneNumberSettings::loadMultiple();
    static::assertCount(1, $settings);
    /** @var \Drupal\sms\Entity\PhoneNumberSettingsInterface $setting */
    $setting = reset($settings);
    $expected_message = 'This is a custom confirmation message from [site:name]. Confirmation code: [sms-message:verification-code]';

    static::assertEquals($expected_message, $setting->getVerificationMessage());
  }

  /**
   * Tests that the users' phone numbers verification status is migrated.
   */
  public function testPhoneNumberMigration(): void {
    $this->loadFixture($this->smsUserFixtureFilePath());

    // Set up phone number verifications.
    $this->installEntitySchema('sms');
    $this->installEntitySchema('sms_phone_number_verification');

    $this->executeMigrations($this->getMigrationsToTest());

    $user = User::load(40);
    static::assertEquals('1234567890', $user->get('phone_number')->value);
    $this->assertVerifiedPhoneNumber($user, '1234567890');

    $user = User::load(41);
    static::assertEquals('87654321190', $user->get('phone_number')->value);
    $this->assertUnVerifiedPhoneNumber($user, '87654321190');
    $this->assertVerificationCode('87654321190', '8002');

    // No phone number for user 15.
    $user = User::load(42);
    static::assertEquals('', $user->get('phone_number')->value);
    $this->assertNoVerifiedPhoneNumber($user);
  }

  /**
   * Tests that conditions are reverted after rollback.
   */
  public function testRollBack(): void {
    $this->loadFixture($this->smsUserFixtureFilePath());
    $this->installEntitySchema('sms');
    $this->installEntitySchema('sms_phone_number_verification');

    // Create an entity form display.
    EntityFormDisplay::create([
      'targetEntityType' => 'user',
      'bundle' => 'user',
      'mode' => 'default',
    ])->setStatus(TRUE)->save();

    $this->executeMigrations($this->getMigrationsToTest());

    $this->assertVerifiedPhoneNumber(User::load(40), '1234567890');
    // Test that the default entity form display has the field added.
    $entity_form_display = EntityFormDisplay::load('user.user.default');
    static::assertNotNull($entity_form_display->getComponent('phone_number'));

    // Rollback migration and check that verifications, phone number settings
    // and phone number fields are removed.
    $this->rollBackMigrations($this->getMigrationsToRollBack());

    // Assert no phone number verifications, phone number settings or phone
    // number fields exist.
    static::assertEquals([], PhoneNumberVerification::loadMultiple());
    static::assertEquals([], PhoneNumberSettings::loadMultiple());
    static::assertNull(FieldConfig::loadByName('user', 'user', 'phone_number'));
    static::assertNull(FieldStorageConfig::loadByName('user', 'phone_number'));

    // Test that the display field is removed.
    $entity_form_display = EntityFormDisplay::load('user.user.default');
    static::assertNull($entity_form_display->getComponent('phone_number'));
  }

  /**
   * Asserts that the specified user has a verified phone number.
   */
  protected function assertVerifiedPhoneNumber(UserInterface $user, $number) {
    $phone_numbers = $this->container->get('sms.phone_number')->getPhoneNumbers($user, TRUE);
    $phone_number = reset($phone_numbers);
    static::assertEquals($number, $phone_number, "Phone number '$number' is verified.");
  }

  /**
   * Asserts that the specified user has an unverified phone number.
   */
  protected function assertUnVerifiedPhoneNumber(UserInterface $user, $number) {
    $phone_numbers = $this->container->get('sms.phone_number')->getPhoneNumbers($user, FALSE);
    $phone_number = reset($phone_numbers);
    static::assertEquals($number, $phone_number, "Phone number '$number' is unverified.");
  }

  /**
   * Asserts that the specified user has no phone number verified or unverified.
   */
  protected function assertNoVerifiedPhoneNumber(UserInterface $user) {
    $phone_numbers = $this->container->get('sms.phone_number')->getPhoneNumbers($user);
    static::assertEquals([], $phone_numbers, "No phone numbers for user {$user->id()}.");
  }

  /**
   * Asserts that the specified number has a pending verification code.
   */
  protected function assertVerificationCode($number, $code) {
    $verification = $this->container->get('sms.phone_number.verification')->getPhoneVerificationByPhoneNumber($number, FALSE);
    $verification = reset($verification);
    return static::assertEquals($code, $verification->getCode());
  }

  /**
   * Rolls back a specified migration.
   */
  protected function rollBackMigrations(array $ids) {
    foreach ($ids as $id) {
      $this->migration = $this->getMigration($id);
      $this->prepareMigration($this->migration);
      (new MigrateExecutable($this->migration, $this))->rollback();
    }
  }

  /**
   * Provides the relative path to the fixture that sets up the database.
   */
  abstract protected function smsUserFixtureFilePath();

  /**
   * Provides the relative path to the fixture that adds confirmation message.
   */
  abstract protected function confirmationMessageFixturePath();

  /**
   * Returns the list of D6 or D7 sms_user phone number migrations to test.
   */
  abstract protected function getMigrationsToTest();

  /**
   * Returns the list of migrations to rollback for the rollback test.
   */
  abstract protected function getMigrationsToRollback();

}
