<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms\Kernel\Migrate;

use Drupal\migrate\Exception\RequirementsException;
use Drupal\Tests\migrate_drupal\Kernel\d6\MigrateDrupal6TestBase;

/**
 * Tests Drupal 6 SMS User phone number migrations.
 *
 * Actual tests are in the trait MigratePhoneNumberTestTrait.
 *
 * @group SMS Framework
 *
 * @see \Drupal\Tests\sms\Kernel\Migrate\MigratePhoneNumberTestTrait
 */
final class MigrateD6SmsPhoneNumberTest extends MigrateDrupal6TestBase {

  use MigratePhoneNumberTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'sms',
    'telephone',
    'dynamic_entity_reference',
    'filter',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->loadFixture(__DIR__ . '/../../../fixtures/migrate/drupal6.php');
  }

  /**
   * Tests that the requirements for the d7_sms_number migration are enforced.
   */
  public function testMigrationRequirements(): void {
    $this->expectException(RequirementsException::class);
    $this->expectExceptionMessage('Missing migrations d6_user, phone_number_settings.');
    $this->getMigration('d6_sms_number')->checkRequirements();
  }

  /**
   * {@inheritdoc}
   */
  protected function getMigrationsToTest(): array {
    return [
      'd6_filter_format',
      'd6_user_role',
      'd6_user',
      'phone_number_settings',
      'd6_sms_number',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getMigrationsToRollback(): array {
    return [
      'd6_sms_number',
      'phone_number_settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function smsUserFixtureFilePath(): string {
    return __DIR__ . '/../../../fixtures/migrate/sms_user_drupal6.php';
  }

  /**
   * {@inheritdoc}
   */
  protected function confirmationMessageFixturePath(): string {
    return __DIR__ . '/../../../fixtures/migrate/sms_confirmation_message_d6.php';
  }

}
