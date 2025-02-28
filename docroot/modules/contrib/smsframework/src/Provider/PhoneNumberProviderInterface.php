<?php

declare(strict_types = 1);

namespace Drupal\sms\Provider;

use Drupal\Core\Entity\EntityInterface;
use Drupal\sms\Message\SmsMessageInterface;

/**
 * Interface for phone number provider.
 */
interface PhoneNumberProviderInterface {

  /**
   * Gets phone numbers for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An entity to get phone numbers.
   * @param bool|null $verified
   *   Whether the returned phone numbers must be verified, or NULL to get all
   *   phone numbers regardless of status.
   *
   * @return string[]
   *   An array of phone numbers.
   */
  public function getPhoneNumbers(EntityInterface $entity, $verified = TRUE);

  /**
   * Sends an SMS to an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to send an SMS.
   * @param \Drupal\sms\Message\SmsMessageInterface $sms_message
   *   The SMS message to send to the entity.
   *
   * @return \Drupal\sms\Message\SmsMessageResultInterface|false
   *   The message result from the gateway.
   *
   * @throws \Drupal\sms\Exception\NoPhoneNumberException
   *   Thrown if entity does not have a phone number.
   */
  public function sendMessage(EntityInterface $entity, SmsMessageInterface $sms_message);

}
