<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms\Kernel;

use Drupal\field\FieldStorageConfigInterface;
use Drupal\sms\Entity\PhoneNumberSettings;
use Drupal\sms\Entity\PhoneNumberSettingsInterface;
use Drupal\sms\Entity\PhoneNumberVerificationInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\entity_test\Entity\EntityTest;

/**
 * Tests verification maintenance executed during cron.
 *
 * @group SMS Framework
 */
final class SmsFrameworkVerificationMaintenanceTest extends SmsFrameworkKernelBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'sms', 'sms_test_gateway', 'entity_test', 'user', 'field', 'telephone',
    'dynamic_entity_reference',
  ];

  /**
   * Phone number settings for entity_test entity type.
   *
   * @var \Drupal\sms\Entity\PhoneNumberSettingsInterface
   */
  private PhoneNumberSettingsInterface $phoneNumberSettings;

  /**
   * A telephone field for testing.
   *
   * @var \Drupal\field\FieldStorageConfigInterface
   */
  private FieldStorageConfigInterface $phoneField;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('sms_phone_number_verification');

    $this->phoneField = FieldStorageConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => mb_strtolower($this->randomMachineName()),
      'type' => 'telephone',
    ]);
    $this->phoneField->save();

    FieldConfig::create([
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'field_name' => $this->phoneField->getName(),
    ])->save();

    $this->phoneNumberSettings = PhoneNumberSettings::create();
    $this->phoneNumberSettings
      ->setPhoneNumberEntityTypeId('entity_test')
      ->setPhoneNumberBundle('entity_test')
      ->setFieldName('phone_number', $this->phoneField->getName())
      ->setPurgeVerificationPhoneNumber(FALSE)
      ->setVerificationCodeLifetime(3600)
      ->setVerificationMessage($this->randomString())
      ->save();

    $gateway = $this->createMemoryGateway(['skip_queue' => TRUE]);
    $this->setFallbackGateway($gateway);

    $this->testEntity = $this->createEntityWithPhoneNumber($this->phoneNumberSettings, ['+123123123']);
  }

  /**
   * Test unverified verification which have not expired.
   */
  public function testVerificationUnverifiedNotExpired(): void {
    $this->getLastVerification()
      ->setStatus(FALSE)
      ->save();
    $this->container->get('cron')->run();
    static::assertTrue($this->getLastVerification() instanceof PhoneNumberVerificationInterface);
  }

  /**
   * Test unverified verification which have expired are deleted.
   */
  public function testVerificationUnverifiedExpired(): void {
    $this->getLastVerification()
      ->setStatus(FALSE)
      ->set('created', 0)
      ->save();
    $this->container->get('cron')->run();
    static::assertFalse($this->getLastVerification());
  }

  /**
   * Test unverified verification which have expired do not purge field data.
   */
  public function testVerificationUnverifiedExpiredNoPurgeFieldData(): void {
    $this->getLastVerification()
      ->setStatus(FALSE)
      ->set('created', 0)
      ->save();
    $this->container->get('cron')->run();
    $this->testEntity = EntityTest::load($this->testEntity->id());
    static::assertNotEmpty($this->testEntity->{$this->phoneField->getName()});
  }

  /**
   * Test unverified verification which have expired purge field data.
   */
  public function testVerificationUnverifiedExpiredPurgeFieldData(): void {
    $this->phoneNumberSettings
      ->setPurgeVerificationPhoneNumber(TRUE)
      ->save();
    $this->getLastVerification()
      ->setStatus(FALSE)
      ->set('created', 0)
      ->save();
    $this->container->get('cron')->run();
    $this->testEntity = EntityTest::load($this->testEntity->id());
    static::assertEmpty($this->testEntity->{$this->phoneField->getName()});
  }

  /**
   * Test verified verification.
   */
  public function testVerificationVerifiedExpired(): void {
    $this->getLastVerification()
      ->setStatus(TRUE)
      ->set('created', 0)
      ->save();
    $this->container->get('cron')->run();
    static::assertTrue($this->getLastVerification() instanceof PhoneNumberVerificationInterface);
  }

}
