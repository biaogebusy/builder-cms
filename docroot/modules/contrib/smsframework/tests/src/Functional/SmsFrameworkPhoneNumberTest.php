<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms\Functional;

use Drupal\Core\Entity\EntityInterface;
use Drupal\sms\Message\SmsMessageInterface;

/**
 * General phone number verification tests.
 *
 * @group SMS Framework
 */
final class SmsFrameworkPhoneNumberTest extends SmsFrameworkBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $test_gateway = $this->createMemoryGateway(['skip_queue' => TRUE]);
    $this->setFallbackGateway($test_gateway);
  }

  /**
   * Test verification code creation on entity postsave.
   *
   * @see _sms_entity_postsave()
   */
  public function testPhoneNumberVerificationCreated(): void {
    $phone_number_settings = $this->createPhoneNumberSettings('entity_test', 'entity_test');

    $phone_numbers = ['+123123123', '+456456456', '+789789789'];
    for ($quantity = 1; $quantity < 3; $quantity++) {
      $test_entity = $this->createEntityWithPhoneNumber($phone_number_settings, array_slice($phone_numbers, 0, $quantity));

      static::assertEquals($quantity, $this->countVerificationCodes($test_entity), 'There is ' . $quantity . ' verification code.');

      // Ensure post-save did not create verification codes if one already
      // exists.
      $test_entity->save();
      static::assertEquals($quantity, $this->countVerificationCodes($test_entity), 'Additional verification codes were not created.');
    }
  }

  /**
   * Ensure phone number verification SMS sent.
   *
   * @see _sms_entity_postsave()
   */
  public function testPhoneNumberVerificationMessage(): void {
    $test_gateway = $this->createMemoryGateway(['skip_queue' => TRUE]);
    $this->setFallbackGateway($test_gateway);

    $phone_numbers = ['+123123123'];
    $phone_number_settings = $this->createPhoneNumberSettings('entity_test', 'entity_test');
    $this->createEntityWithPhoneNumber($phone_number_settings, $phone_numbers);

    $sms_message = $this->getLastTestMessage($test_gateway);
    static::assertTrue($sms_message instanceof SmsMessageInterface, 'SMS verification message sent.');
    static::assertEquals($sms_message->getRecipients(), $phone_numbers, 'Sent to correct phone number.');

    $phone_verification = $this->getLastVerification();
    $data['sms_verification_code'] = $phone_verification->getCode();
    $message = \Drupal::token()->replace(
      $phone_number_settings->getVerificationMessage(),
      $data,
    );
    static::assertEquals($sms_message->getMessage(), $message, 'Sent correct message.');
  }

  /**
   * Ensure phone number verification are deleted.
   *
   * @see sms_entity_delete()
   */
  public function testPhoneNumberVerificationDeleted(): void {
    $phone_number_settings = $this->createPhoneNumberSettings('entity_test', 'entity_test');

    $entities = [];
    for ($i = 0; $i < 3; $i++) {
      $phone_numbers = ['+123123123', '+456456456'];
      $entities[] = $this->createEntityWithPhoneNumber($phone_number_settings, $phone_numbers);
    }

    static::assertEquals(6, $this->countVerificationCodes());
    $entities[1]->delete();
    static::assertEquals(4, $this->countVerificationCodes(), 'Verification codes deleted.');
  }

  /**
   * Count verification codes in database.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The entity to count verification codes for, or NULL to count all codes.
   *
   * @return int
   *   Count number of verification codes.
   */
  protected function countVerificationCodes(?EntityInterface $entity = NULL): int {
    $query = \Drupal::entityTypeManager()
      ->getStorage('sms_phone_number_verification')
      ->getQuery()
      ->accessCheck(FALSE);

    if ($entity) {
      $query->condition('entity__target_type', $entity->getEntityTypeId());
      $query->condition('entity__target_id', $entity->id());
    }

    return (int) $query->count()
      ->execute();
  }

}
