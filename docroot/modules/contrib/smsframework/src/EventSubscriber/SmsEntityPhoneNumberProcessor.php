<?php

declare(strict_types = 1);

namespace Drupal\sms\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\sms\Event\SmsEvents;
use Drupal\sms\Event\SmsEntityPhoneNumber;
use Drupal\sms\Exception\PhoneNumberSettingsException;
use Drupal\sms\Provider\PhoneNumberVerificationInterface;

/**
 * Resolve phone numbers for an entity using phone verification system.
 */
class SmsEntityPhoneNumberProcessor implements EventSubscriberInterface {

  /**
   * The phone number verification service.
   *
   * @var \Drupal\sms\Provider\PhoneNumberVerificationInterface
   */
  protected $phoneNumberVerification;

  /**
   * Constructs a new SmsEntityPhoneNumberProcessor object.
   *
   * @param \Drupal\sms\Provider\PhoneNumberVerificationInterface $phone_number_verification
   *   The phone number verification service.
   */
  public function __construct(PhoneNumberVerificationInterface $phone_number_verification) {
    $this->phoneNumberVerification = $phone_number_verification;
  }

  /**
   * Get phone numbers for an entity using phone verification system.
   *
   * @param \Drupal\sms\Event\SmsEntityPhoneNumber $event
   *   The entity phone number event.
   */
  public function entityPhoneNumbers(SmsEntityPhoneNumber $event) {
    $entity = $event->getEntity();

    try {
      $phone_number_settings = $this->phoneNumberVerification
        ->getPhoneNumberSettingsForEntity($entity);
      $field_name = $phone_number_settings->getFieldName('phone_number');
      if (!$field_name) {
        return;
      }
    }
    catch (PhoneNumberSettingsException $e) {
      return;
    }

    $phone_numbers = [];
    if (isset($entity->{$field_name})) {
      foreach ($entity->{$field_name} as $item) {
        $phone_numbers[] = $item->value;
      }
    }

    $verified = $event->getRequiresVerification();
    foreach ($phone_numbers as $phone_number) {
      if (!isset($verified)) {
        $event->addPhoneNumber($phone_number);
      }
      else {
        $verification = $this->phoneNumberVerification
          ->getPhoneVerificationByEntity($entity, $phone_number);
        if ($verification && $verification->getStatus() == $verified) {
          $event->addPhoneNumber($phone_number);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[SmsEvents::ENTITY_PHONE_NUMBERS][] = ['entityPhoneNumbers', 1024];
    return $events;
  }

}
