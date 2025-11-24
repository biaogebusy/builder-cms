<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\sms\Entity\PhoneNumberSettingsInterface;
use Drupal\sms\Entity\SmsGatewayInterface;
use Drupal\sms\Exception\NoPhoneNumberException;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\sms\Entity\PhoneNumberSettings;
use Drupal\sms\Message\SmsMessage;
use Drupal\sms\Provider\PhoneNumberProviderInterface;
use Drupal\sms\Provider\PhoneNumberVerificationInterface;

/**
 * Tests Phone Number Provider.
 *
 * @group SMS Framework
 * @coversDefaultClass \Drupal\sms\Provider\PhoneNumberProvider
 */
final class SmsFrameworkPhoneNumberProviderTest extends SmsFrameworkKernelBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'sms', 'entity_test', 'user', 'field', 'telephone',
    'dynamic_entity_reference', 'sms_test_gateway',
  ];

  /**
   * The phone number provider.
   *
   * @var \Drupal\sms\Provider\PhoneNumberProviderInterface
   */
  private PhoneNumberProviderInterface $phoneNumberProvider;

  /**
   * Phone number verification provider.
   *
   * @var \Drupal\sms\Provider\PhoneNumberVerificationInterface
   */
  private PhoneNumberVerificationInterface $phoneNumberVerificationProvider;

  /**
   * A telephone field for testing.
   *
   * @var \Drupal\field\FieldStorageConfigInterface
   */
  private FieldStorageConfigInterface $phoneField;

  /**
   * Phone number settings for entity_test entity type.
   *
   * @var \Drupal\sms\Entity\PhoneNumberSettingsInterface
   */
  private PhoneNumberSettingsInterface $phoneNumberSettings;

  /**
   * The default gateway.
   *
   * @var \Drupal\sms\Entity\SmsGatewayInterface
   */
  private SmsGatewayInterface $gateway;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('sms_phone_number_verification');

    $this->gateway = $this->createMemoryGateway(['skip_queue' => TRUE]);
    $this->setFallbackGateway($this->gateway);

    $this->phoneNumberProvider = $this->container->get('sms.phone_number');

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
      ->setVerificationMessage($this->randomString())
      ->save();
  }

  /**
   * Tests phone numbers.
   *
   * @covers ::getPhoneNumbers
   */
  public function testGetPhoneNumbersUnverified(): void {
    $phone_numbers_all = ['+123123123', '+456456456'];

    // Test zero, one, multiple phone numbers.
    for ($i = 0; $i < 3; $i++) {
      $phone_numbers = array_slice($phone_numbers_all, 0, $i);
      $entity = $this->createEntityWithPhoneNumber($this->phoneNumberSettings, $phone_numbers);

      $return = $this->phoneNumberProvider->getPhoneNumbers($entity, NULL);
      static::assertEquals($phone_numbers, $return);

      $return = $this->phoneNumberProvider->getPhoneNumbers($entity, FALSE);
      static::assertEquals($phone_numbers, $return);

      $return = $this->phoneNumberProvider->getPhoneNumbers($entity, TRUE);
      static::assertEquals([], $return);
    }
  }

  /**
   * Tests phone numbers.
   *
   * @covers ::getPhoneNumbers
   */
  public function testGetPhoneNumbersVerified(): void {
    $phone_numbers_all = ['+123123123', '+456456456'];

    // Test zero, one, multiple phone numbers.
    for ($i = 0; $i < 3; $i++) {
      $phone_numbers = array_slice($phone_numbers_all, 0, $i);
      $entity = $this->createEntityWithPhoneNumber($this->phoneNumberSettings, $phone_numbers);

      // Ensures test verifications don't leak between entities. array_values()
      // resets array keys since they are not important, assertEquals() normally
      // asserts keys.
      $phone_number_verified = array_values(array_slice($phone_numbers, 0, 1, TRUE));
      $phone_number_unverified = array_values(array_slice($phone_numbers, 1, $i, TRUE));

      // Verify first phone number.
      if (!empty($phone_number_verified)) {
        $this->verifyPhoneNumber($entity, reset($phone_number_verified));
      }

      $return = $this->phoneNumberProvider->getPhoneNumbers($entity, NULL);
      static::assertEquals($phone_numbers, $return);

      $return = $this->phoneNumberProvider->getPhoneNumbers($entity, FALSE);
      static::assertEquals($phone_number_unverified, $return, $entity->id());

      $return = $this->phoneNumberProvider->getPhoneNumbers($entity, TRUE);
      static::assertEquals($phone_number_verified, $return);
    }
  }

  /**
   * Tests getting a phone number, where no verification exists.
   *
   * Normally a phone number verification is maintained as field values change,
   * via updatePhoneVerificationByEntity. However field values may exist before
   * a phone number settings map exist, or values may be entered in manually,
   * such as with migrate with hooks turned off.
   *
   * @covers ::getPhoneNumbers
   */
  public function testGetPhoneNumbersNoVerification(): void {
    $phoneNumberSettings = $this->phoneNumberSettings;
    $this->phoneNumberSettings->delete();

    // Explicitly don't use createEntityWithPhoneNumber because we dont have
    // phone number settings yet.
    $entity = EntityTest::create([
      $this->phoneField->getName() => '+123123123',
    ]);
    $entity->save();

    // Recreate settings.
    $phoneNumberSettings->save();

    // Must check for verified:
    static::assertEquals([], $this->phoneNumberProvider->getPhoneNumbers($entity, TRUE));
  }

  /**
   * Tests SMS message sent to entities with unverified phone number.
   *
   * @covers ::sendMessage
   */
  public function testSendMessageUnverified(): void {
    $phone_numbers = ['+123123123'];
    $entity = $this->createEntityWithPhoneNumber($this->phoneNumberSettings, $phone_numbers);
    $this->resetTestMessages();
    $sms_message = new SmsMessage();
    $sms_message
      ->setSenderNumber('+999888777')
      ->setMessage($this->randomString());
    $this->expectException(NoPhoneNumberException::class);
    $this->phoneNumberProvider->sendMessage($entity, $sms_message);
  }

  /**
   * Tests SMS message sent to entities with verified phone number.
   *
   * @covers ::sendMessage
   */
  public function testSendMessageVerified(): void {
    $phone_numbers = ['+123123123'];
    $entity = $this->createEntityWithPhoneNumber($this->phoneNumberSettings, $phone_numbers);
    $this->resetTestMessages();
    $this->verifyPhoneNumber($entity, $phone_numbers[0]);

    $sms_message = new SmsMessage();
    $sms_message
      ->setSenderNumber('+999888777')
      ->setMessage($this->randomString());
    $this->phoneNumberProvider->sendMessage($entity, $sms_message);
    static::assertCount(1, $this->getTestMessages($this->gateway));
  }

  /**
   * Ensure default behaviour is to send one phone number per entity.
   *
   * @covers ::sendMessage
   */
  public function testSendMessageOneMessage(): void {
    $phone_numbers = ['+123123123', '+456456456'];
    $entity = $this->createEntityWithPhoneNumber($this->phoneNumberSettings, $phone_numbers);
    $this->resetTestMessages();
    $this->verifyPhoneNumber($entity, $phone_numbers[0]);
    $this->verifyPhoneNumber($entity, $phone_numbers[1]);

    $sms_message = new SmsMessage();
    $sms_message
      ->setMessage($this->randomString());
    $this->phoneNumberProvider->sendMessage($entity, $sms_message);

    $message = $this->getLastTestMessage($this->gateway);
    static::assertEquals([$phone_numbers[0]], $message->getRecipients(), 'The SMS message is using the first phone number from the entity.');
  }

}
