<?php

declare(strict_types = 1);

namespace Drupal\sms\Provider;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\sms\Direction;
use Drupal\sms\Entity\SmsMessage as SmsMessageEntity;
use Drupal\sms\Event\SmsEvents;
use Drupal\sms\Event\SmsEntityPhoneNumber;
use Drupal\sms\Exception\NoPhoneNumberException;
use Drupal\sms\Message\SmsMessageInterface;

/**
 * Phone number provider.
 */
class PhoneNumberProvider implements PhoneNumberProviderInterface {

  /**
   * Constructs a new PhoneNumberProvider object.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\sms\Provider\SmsProviderInterface $smsProvider
   *   The SMS provider.
   */
  public function __construct(
    protected EventDispatcherInterface $eventDispatcher,
    protected SmsProviderInterface $smsProvider,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getPhoneNumbers(EntityInterface $entity, $verified = TRUE) {
    $event = new SmsEntityPhoneNumber($entity, $verified);
    /** @var \Drupal\sms\Event\SmsEntityPhoneNumber $event */
    $event = $this->eventDispatcher
      ->dispatch($event, SmsEvents::ENTITY_PHONE_NUMBERS);
    return $event->getPhoneNumbers();
  }

  /**
   * {@inheritdoc}
   */
  public function sendMessage(EntityInterface $entity, SmsMessageInterface $sms_message) {
    if (!$phone_numbers = $this->getPhoneNumbers($entity)) {
      throw new NoPhoneNumberException('Attempted to send an SMS to entity without a phone number.');
    }

    $sms_message = SmsMessageEntity::convertFromSmsMessage($sms_message)
      ->addRecipient(reset($phone_numbers))
      ->setRecipientEntity($entity)
      ->setDirection(Direction::OUTGOING);

    $this->smsProvider
      ->queue($sms_message);
  }

}
