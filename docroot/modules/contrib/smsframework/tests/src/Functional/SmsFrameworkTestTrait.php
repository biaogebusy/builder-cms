<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms\Functional;

use Drupal\Core\Entity\EntityInterface;
use Drupal\sms\Entity\PhoneNumberSettingsInterface;
use Drupal\sms\Entity\SmsGateway;
use Drupal\sms\Entity\SmsGatewayInterface;
use Drupal\sms\Message\SmsMessage;
use Drupal\sms\Message\SmsDeliveryReport;
use Drupal\sms\Message\SmsMessageInterface;
use Drupal\sms\Message\SmsMessageResult;

/**
 * Shared SMS Framework helpers for kernel and web tests.
 */
trait SmsFrameworkTestTrait {

  /**
   * Sets the fallback gateway.
   *
   * @param \Drupal\sms\Entity\SmsGatewayInterface|null $sms_gateway
   *   The new site fallback SMS Gateway, or NULL to unset.
   */
  protected function setFallbackGateway(?SmsGatewayInterface $sms_gateway = NULL) {
    $sms_gateway = $sms_gateway?->id();
    $this->config('sms.settings')
      ->set('fallback_gateway', $sms_gateway)
      ->save();
  }

  /**
   * Creates a memory gateway.
   *
   * @param array $values
   *   Additional values to use when creating the gateway.
   *
   * @return \Drupal\sms\Entity\SmsGatewayInterface
   *   A saved memory gateway.
   */
  protected function createMemoryGateway(array $values = []): SmsGatewayInterface {
    $id = $values['id'] ?? mb_strtolower($this->randomMachineName(16));
    $gateway = SmsGateway::create($values + [
      'plugin' => 'memory',
      'id' => $id,
      'label' => $this->randomString(),
      'settings' => ['gateway_id' => $id],
    ] + $values);
    $gateway->enable();
    $gateway->save();
    return $gateway;
  }

  /**
   * Get all SMS messages sent to a 'Memory' gateway.
   *
   * @param \Drupal\sms\Entity\SmsGatewayInterface $sms_gateway
   *   A gateway plugin instance.
   *
   * @return \Drupal\sms\Message\SmsMessageInterface[]
   *   An array of SMS messages sent to a 'Memory' gateway.
   */
  public function getTestMessages(SmsGatewayInterface $sms_gateway): array {
    $gateway_id = $sms_gateway->id();
    $sms_messages = \Drupal::state()->get('sms_test_gateway.memory.send', []);
    return $sms_messages[$gateway_id] ?? [];
  }

  /**
   * Get the last SMS message sent to 'Memory' gateway.
   *
   * @param \Drupal\sms\Entity\SmsGatewayInterface $sms_gateway
   *   A gateway plugin.
   *
   * @return \Drupal\sms\Message\SmsMessageInterface|false
   *   The last SMS message, or FALSE if no messages have been sent.
   */
  public function getLastTestMessage(SmsGatewayInterface $sms_gateway) {
    $gateway_id = $sms_gateway->id();
    $sms_messages = \Drupal::state()->get('sms_test_gateway.memory.send', []);
    return isset($sms_messages[$gateway_id]) ? end($sms_messages[$gateway_id]) : FALSE;
  }

  /**
   * Resets SMS messages stored in memory by 'Memory' gateway.
   *
   * @param \Drupal\sms\Entity\SmsGatewayInterface|null $sms_gateway
   *   A gateway plugin, or NULL to reset all messages.
   */
  public function resetTestMessages(?SmsGatewayInterface $sms_gateway = NULL) {
    $sms_messages = \Drupal::state()->get('sms_test_gateway.memory.send', []);
    if ($sms_gateway) {
      $sms_messages[$sms_gateway->id()] = [];
    }
    else {
      $sms_messages = [];
    }
    \Drupal::state()->set('sms_test_gateway.memory.send', $sms_messages);
  }

  /**
   * Get all messages received by a gateway.
   *
   * @param \Drupal\sms\Entity\SmsGatewayInterface $sms_gateway
   *   A gateway plugin instance.
   *
   * @return \Drupal\sms\Message\SmsMessageInterface[]
   *   An array of messages received by a gateway.
   */
  protected function getIncomingMessages(SmsGatewayInterface $sms_gateway): array {
    $gateway_id = $sms_gateway->id();
    $sms_messages = \Drupal::state()->get('sms_test_gateway.memory.incoming', []);
    return $sms_messages[$gateway_id] ?? [];
  }

  /**
   * Get the last message sent to gateway.
   *
   * @param \Drupal\sms\Entity\SmsGatewayInterface $sms_gateway
   *   A gateway plugin.
   *
   * @return \Drupal\sms\Message\SmsMessageInterface|false
   *   The last message, or FALSE if no messages were received.
   */
  protected function getLastIncomingMessage(SmsGatewayInterface $sms_gateway) {
    $gateway_id = $sms_gateway->id();
    $sms_messages = \Drupal::state()->get('sms_test_gateway.memory.incoming', []);
    return isset($sms_messages[$gateway_id]) ? end($sms_messages[$gateway_id]) : FALSE;
  }

  /**
   * Resets incoming messages stored in memory by gateway.
   *
   * @param \Drupal\sms\Entity\SmsGatewayInterface|null $sms_gateway
   *   A gateway plugin, or NULL to reset all messages.
   */
  protected function resetIncomingMessages(?SmsGatewayInterface $sms_gateway = NULL) {
    $sms_messages = \Drupal::state()->get('sms_test_gateway.memory.incoming', []);
    if ($sms_gateway) {
      $sms_messages[$sms_gateway->id()] = [];
    }
    else {
      $sms_messages = [];
    }
    \Drupal::state()->set('sms_test_gateway.memory.incoming', $sms_messages);
  }

  /**
   * Gets all SMS reports for messages sent to 'Memory' gateway.
   *
   * @param \Drupal\sms\Entity\SmsGatewayInterface $sms_gateway
   *   A gateway plugin.
   *
   * @return \Drupal\sms\Message\SmsDeliveryReportInterface[]
   *   An array of SMS reports for messages sent to 'Memory' gateway.
   */
  protected function getTestMessageReports(SmsGatewayInterface $sms_gateway): array {
    $gateway_id = $sms_gateway->id();
    $sms_reports = \Drupal::state()->get('sms_test_gateway.memory.report', []);
    return $sms_reports[$gateway_id] ?? [];
  }

  /**
   * Gets the last SMS report for messages sent to 'Memory' gateway.
   *
   * @param \Drupal\sms\Entity\SmsGatewayInterface $sms_gateway
   *   A gateway plugin.
   *
   * @return \Drupal\sms\Message\SmsDeliveryReportInterface|false
   *   The last SMS message, or FALSE if no messages have been sent.
   */
  protected function getLastTestMessageReport(SmsGatewayInterface $sms_gateway) {
    $gateway_id = $sms_gateway->id();
    $sms_reports = \Drupal::state()->get('sms_test_gateway.memory.report', []);
    return isset($sms_reports[$gateway_id]) ? end($sms_reports[$gateway_id]) : FALSE;
  }

  /**
   * Gets an SMS report for message with message ID.
   *
   * @param string $message_id
   *   The message ID.
   * @param \Drupal\sms\Entity\SmsGatewayInterface $sms_gateway
   *   A gateway plugin.
   *
   * @return \Drupal\sms\Message\SmsDeliveryReportInterface
   *   The last SMS message, or FALSE if no messages have been sent.
   */
  protected function getTestMessageReport($message_id, SmsGatewayInterface $sms_gateway) {
    $gateway_id = $sms_gateway->id();
    $reports = \Drupal::state()->get('sms_test_gateway.memory.report', []);
    return $reports[$gateway_id][$message_id];
  }

  /**
   * Resets the SMS reports stored in memory by 'Memory' gateway.
   */
  protected function resetTestMessageReports(): void {
    \Drupal::state()->set('sms_test_gateway.memory.report', []);
  }

  /**
   * Creates an entity, and optionally adds phone numbers.
   *
   * @param \Drupal\sms\Entity\PhoneNumberSettingsInterface $phone_number_settings
   *   Phone number settings.
   * @param array $phone_numbers
   *   An array of phone numbers to add to the entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   An entity with phone numbers.
   */
  protected function createEntityWithPhoneNumber(PhoneNumberSettingsInterface $phone_number_settings, array $phone_numbers = []): EntityInterface {
    $entity_type = $phone_number_settings->getPhoneNumberEntityTypeId();
    $field_name = $phone_number_settings->getFieldName('phone_number');
    $entity_type_manager = \Drupal::entityTypeManager();
    $test_entity = $entity_type_manager->getStorage($entity_type)
      ->create([
        'name' => $this->randomMachineName(),
      ]);

    foreach ($phone_numbers as $phone_number) {
      $test_entity->{$field_name}[] = $phone_number;
    }

    $test_entity->save();
    return $test_entity;
  }

  /**
   * Forces verification of a phone number for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An entity to verify phone number.
   * @param string $phone_number
   *   A phone number.
   */
  protected function verifyPhoneNumber(EntityInterface $entity, $phone_number) {
    $verifications = \Drupal::entityTypeManager()
      ->getStorage('sms_phone_number_verification')
      ->loadByProperties([
        'entity__target_type' => $entity->getEntityTypeId(),
        'entity__target_id' => $entity->id(),
        'phone' => $phone_number,
      ]);
    $verification = reset($verifications);
    $verification->setStatus(TRUE)
      ->save();
  }

  /**
   * Gets the last phone number verification that was created.
   *
   * @return \Drupal\sms\Entity\PhoneNumberVerificationInterface|false
   *   The last verification created, or FALSE if no verifications exist.
   */
  protected function getLastVerification() {
    $verification_storage = \Drupal::entityTypeManager()
      ->getStorage('sms_phone_number_verification');

    $verification_ids = $verification_storage->getQuery()
      ->sort('created', 'DESC')
      ->range(0, 1)
      ->accessCheck(FALSE)
      ->execute();
    $verifications = $verification_storage->loadMultiple($verification_ids);

    return reset($verifications);
  }

  /**
   * Create a result and reports for a message.
   *
   * @param \Drupal\sms\Message\SmsMessageInterface $sms_message
   *   A message object.
   *
   * @return \Drupal\sms\Message\SmsMessageResult
   *   A message result with reports for each message recipient.
   */
  protected function createMessageResult(SmsMessageInterface $sms_message): SmsMessageResult {
    $reports = array_map(
      static function ($recipient) {
        return (new SmsDeliveryReport())
          ->setRecipient($recipient);
      },
      $sms_message->getRecipients(),
    );

    return (new SmsMessageResult())
      ->setErrorMessage($this->randomString())
      ->setReports($reports);
  }

  /**
   * Generates a random SMS message by the specified user.
   *
   * @param int $uid
   *   (optional) The user ID to generate the message as. Defaults to 1.
   *
   * @return \Drupal\sms\Message\SmsMessageInterface
   *   A random SMS message by the specified user.
   */
  protected function randomSmsMessage(int $uid = 1): SmsMessageInterface {
    $phone_numbers = $this->randomPhoneNumbers(1);
    return new SmsMessage($phone_numbers[0], $this->randomPhoneNumbers(), $this->randomString(), [], $uid);
  }

  /**
   * Generates random phone numbers for tests.
   *
   * @param int|null $quantity
   *   Quantity of phone numbers, or NULL to generate at least 2.
   *
   * @return array
   *   An array of phone numbers.
   */
  protected function randomPhoneNumbers($quantity = NULL): array {
    $quantity = $quantity ?? rand(2, 20);
    $numbers = [];
    for ($i = 0; $i < $quantity; $i++) {
      $numbers[] = '+' . rand(1000, 999999999);
    }
    return $numbers;
  }

}
