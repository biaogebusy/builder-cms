<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\sms\Entity\PhoneNumberSettings;
use Drupal\sms\Entity\PhoneNumberSettingsInterface;
use Drupal\sms\Entity\PhoneNumberVerificationInterface;
use Drupal\sms\Entity\SmsGatewayInterface;
use Drupal\sms\Exception\PhoneNumberSettingsException;
use Drupal\sms\Provider\PhoneNumberProviderInterface;
use Drupal\sms\Provider\PhoneNumberVerificationInterface as PhoneNumberVerificationProviderInterface;

/**
 * Tests Phone Number Provider.
 *
 * @group SMS Framework
 * @coversDefaultClass \Drupal\sms\Provider\PhoneNumberVerification
 */
final class SmsFrameworkPhoneNumberVerificationTest extends SmsFrameworkKernelBase {

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
  private PhoneNumberVerificationProviderInterface $phoneNumberVerificationProvider;

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

    $this->phoneNumberVerificationProvider = $this->container->get('sms.phone_number.verification');

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
   * Tests read only phone number config helper.
   *
   * @covers ::getPhoneNumberSettings
   */
  public function testGetPhoneNumberSettings(): void {
    $return = $this->phoneNumberVerificationProvider->getPhoneNumberSettings($this->randomMachineName(), $this->randomMachineName());
    static::assertNull($return, 'Phone number settings does not exist.');

    $return = $this->phoneNumberVerificationProvider->getPhoneNumberSettings('entity_test', $this->randomMachineName());
    static::assertNull($return, 'Phone number settings does not exist.');

    $return = $this->phoneNumberVerificationProvider->getPhoneNumberSettings('entity_test', 'entity_test');
    static::assertTrue($return instanceof PhoneNumberSettingsInterface);
  }

  /**
   * Tests read only phone number config helper via entity with no settings..
   *
   * @covers ::getPhoneNumberSettingsForEntity
   */
  public function testGetPhoneNumberSettingsForEntityNoSettings(): void {
    $test_entity_random_bundle = EntityTest::create([
      'name' => $this->randomMachineName(),
      'type' => $this->randomMachineName(),
    ]);

    $this->expectException(PhoneNumberSettingsException::class);
    $this->phoneNumberVerificationProvider->getPhoneNumberSettingsForEntity($test_entity_random_bundle);
  }

  /**
   * Tests read only phone number config helper via entity.
   *
   * @covers ::getPhoneNumberSettingsForEntity
   */
  public function testGetPhoneNumberSettingsForEntity(): void {
    $entity = $this->createEntityWithPhoneNumber($this->phoneNumberSettings);
    $return = $this->phoneNumberVerificationProvider->getPhoneNumberSettingsForEntity($entity);
    static::assertTrue($return instanceof PhoneNumberSettingsInterface);
  }

  /**
   * Tests get verification by code.
   *
   * @covers ::getPhoneVerificationByCode
   */
  public function testGetPhoneVerificationByCode(): void {
    $this->createEntityWithPhoneNumber($this->phoneNumberSettings, ['+123123123']);
    $verification = $this->getLastVerification();
    $return = $this->phoneNumberVerificationProvider->getPhoneVerificationByCode($verification->getCode());
    static::assertEquals($return->id(), $verification->id());
  }

  /**
   * Tests get verification by non-existent code.
   *
   * @covers ::getPhoneVerificationByCode
   */
  public function testGetPhoneVerificationByFakeCode(): void {
    $this->createEntityWithPhoneNumber($this->phoneNumberSettings, ['+123123123']);
    $return = $this->phoneNumberVerificationProvider->getPhoneVerificationByCode($this->randomMachineName());
    static::assertFalse($return);
  }

  /**
   * Tests get verification by phone number.
   *
   * @covers ::getPhoneVerificationByPhoneNumber
   */
  public function testGetPhoneVerificationByPhoneNumber(): void {
    $phone_number1 = '+123123123';
    $this->createEntityWithPhoneNumber($this->phoneNumberSettings, [$phone_number1]);
    // Decoy:
    $phone_number2 = '+456456456';
    $this->createEntityWithPhoneNumber($this->phoneNumberSettings, [$phone_number2]);

    $return = $this->phoneNumberVerificationProvider->getPhoneVerificationByPhoneNumber($phone_number1, NULL);
    static::assertCount(1, $return);
  }

  /**
   * Tests get verification by phone number with verified option.
   *
   * @covers ::getPhoneVerificationByPhoneNumber
   */
  public function testGetPhoneVerificationByPhoneNumberVerified(): void {
    $phone_number1 = '+123123123';
    $phone_number2 = '+456456456';
    $entity = $this->createEntityWithPhoneNumber($this->phoneNumberSettings, [
      $phone_number1,
      $phone_number2,
    ]);
    $this->verifyPhoneNumber($entity, $phone_number2);

    $return = $this->phoneNumberVerificationProvider->getPhoneVerificationByPhoneNumber($phone_number1, TRUE);
    static::assertCount(0, $return);

    $return = $this->phoneNumberVerificationProvider->getPhoneVerificationByPhoneNumber($phone_number1, FALSE);
    static::assertCount(1, $return);

    $return = $this->phoneNumberVerificationProvider->getPhoneVerificationByPhoneNumber($phone_number2, FALSE);
    static::assertCount(0, $return);

    $return = $this->phoneNumberVerificationProvider->getPhoneVerificationByPhoneNumber($phone_number2, TRUE);
    static::assertCount(1, $return);
  }

  /**
   * Tests get verification by phone number with entity type ID option.
   *
   * @covers ::getPhoneVerificationByPhoneNumber
   */
  public function testGetPhoneVerificationByPhoneNumberEntityType(): void {
    $phone_number = '+123123123';
    $this->createEntityWithPhoneNumber($this->phoneNumberSettings, [$phone_number]);

    $return = $this->phoneNumberVerificationProvider->getPhoneVerificationByPhoneNumber($phone_number, NULL, 'entity_test');
    static::assertCount(1, $return);

    $return = $this->phoneNumberVerificationProvider->getPhoneVerificationByPhoneNumber($phone_number, NULL, $this->randomMachineName());
    static::assertCount(0, $return);
  }

  /**
   * Tests get verification by entity.
   *
   * @covers ::getPhoneVerificationByEntity
   */
  public function testGetPhoneVerificationByEntity(): void {
    $phone_number = '+123123123';
    $entity = $this->createEntityWithPhoneNumber($this->phoneNumberSettings, [$phone_number]);
    $return = $this->phoneNumberVerificationProvider->getPhoneVerificationByEntity($entity, $phone_number);
    static::assertNotFalse($return);
  }

  /**
   * Tests get verification by entity with phone number without verification.
   *
   * @covers ::getPhoneVerificationByEntity
   */
  public function testGetPhoneVerificationByEntityInvalidPhone(): void {
    $entity = $this->createEntityWithPhoneNumber($this->phoneNumberSettings, ['+123123123']);
    $return = $this->phoneNumberVerificationProvider->getPhoneVerificationByEntity($entity, '+456456456');
    static::assertFalse($return);
  }

  /**
   * Tests creating new verification for an entity.
   *
   * @covers ::newPhoneVerification
   */
  public function testNewPhoneVerification(): void {
    $phone_number = '+123123123';
    $entity = $this->createEntityWithPhoneNumber($this->phoneNumberSettings);

    $return = $this->phoneNumberVerificationProvider->newPhoneVerification($entity, $phone_number);
    static::assertTrue($return instanceof PhoneNumberVerificationInterface);

    // Catch the phone verification message.
    $sent_messages = $this->getTestMessages($this->gateway);
    static::assertCount(1, $sent_messages);

    $verification_message = reset($sent_messages);
    static::assertTrue($verification_message->getOption('_is_verification_message'));

    $verification = $this->getLastVerification();
    static::assertEquals($entity->id(), $verification->getEntity()->id());
    static::assertEquals($phone_number, $verification->getPhoneNumber());
  }

}
