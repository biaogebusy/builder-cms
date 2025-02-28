<?php

declare(strict_types = 1);

namespace Drupal\sms_user\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\sms\Event\SmsEvents;
use Drupal\sms\Event\SmsMessageEvent;
use Drupal\sms_user\AccountRegistrationInterface;

/**
 * Event subscriber responding to SMS Framework events.
 */
class SmsEventSubscriber implements EventSubscriberInterface {

  /**
   * The account registration service.
   *
   * @var \Drupal\sms_user\AccountRegistrationInterface
   */
  protected $accountRegistration;

  /**
   * Constructs a new SmsEvents instance.
   *
   * @param \Drupal\sms_user\AccountRegistrationInterface $account_registration
   *   The account registration service.
   */
  public function __construct(AccountRegistrationInterface $account_registration) {
    $this->accountRegistration = $account_registration;
  }

  /**
   * Process an incoming SMS to see if a new account should be created.
   *
   * @param \Drupal\sms\Event\SmsMessageEvent $event
   *   The event.
   */
  public function createAccount(SmsMessageEvent $event) {
    foreach ($event->getMessages() as $sms_message) {
      $this->accountRegistration->createAccount($sms_message);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[SmsEvents::MESSAGE_INCOMING_POST_PROCESS][] = ['createAccount'];
    return $events;
  }

}
