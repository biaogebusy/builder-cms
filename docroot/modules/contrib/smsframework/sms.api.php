<?php

declare(strict_types = 1);

// @codingStandardsIgnoreFile

/**
 * @file
 * SMS Framework hooks.
 */

/**
 * Alter gateway plugin definitions.
 *
 * This hook gives you a chance to modify gateways after all plugin definitions
 * are discovered.
 *
 * @param array $gateways
 *   An array of gateway definitions keyed by plugin ID.
 */
function hook_sms_gateway_info_alter(array &$gateways) {
  $gateways['log']['label'] = new \Drupal\Core\StringTranslation\TranslatableMarkup('The Logger');
}

/**
 * Event subscribers for SMS Framework.
 *
 * Service definition:
 * <code>
 * ```yaml
 *  my_module.my_event_subscriber:
 *    class: Drupal\my_module\EventSubscriber\MySmsEventSubscriber
 *    tags:
 *     - { name: event_subscriber }
 * ```
 * </code>
 *
 * <code>
 * <?php
 * namespace Drupal\my_module\EventSubscriber;
 * ?>
 * </code>
 *
 * @see \Drupal\sms_test\EventSubscriber\SmsTestEventSubscriber
 */
class MySmsEventSubscriber implements \Symfony\Component\EventDispatcher\EventSubscriberInterface {

  /**
   * An example event subscriber.
   *
   * @see \Drupal\sms\Event\SmsEvents::ENTITY_PHONE_NUMBERS
   */
  public function myEntityPhoneNumbers(\Drupal\sms\Event\SmsEntityPhoneNumber $event) {
    // Entity to get phone numbers for.
    $entity = $event->getEntity();
    // Add a phone number for above entity.
    $event->addPhoneNumber('+123456879');
  }

  /**
   * An example event subscriber.
   *
   * @see \Drupal\sms\Event\SmsEvents::MESSAGE_PRE_PROCESS
   */
  public function mySmsMessagePreprocess(\Drupal\sms\Event\SmsMessageEvent $event) {
    $result = [];
    foreach ($event->getMessages() as $message) {
      // Modify or chunk messages.
      $result[] = $message;
    }
    $event->setMessages($result);
  }

  /**
   * An example event subscriber.
   *
   * @see \Drupal\sms\Event\SmsEvents::MESSAGE_POST_PROCESS
   */
  public function mySmsMessagePostProcess(\Drupal\sms\Event\SmsMessageEvent $event) {
    $result = [];
    foreach ($event->getMessages() as $message) {
      // Modify or chunk messages.
      $result[] = $message;
    }
    $event->setMessages($result);
  }

  /**
   * An example event subscriber.
   *
   * @see \Drupal\sms\Event\SmsEvents::MESSAGE_GATEWAY
   */
  public function mySmsMessageGateway(\Drupal\sms\Event\RecipientGatewayEvent $event) {
    // The recipient phone number.
    $event->getRecipient();
    // Add a gateway for a phone number.
    $event->addGateway($a_gateway);
    // Add a gateway with a priority.
    $event->addGateway($a_gateway, 333);
    $event->addGateway($a_gateway, -333);
  }

  /**
   * An example event subscriber.
   *
   * @see \Drupal\sms\Event\SmsEvents::DELIVERY_REPORT_POST_PROCESS
   */
  public function myDeliveryReportPostProcessor(\Drupal\sms\Event\SmsDeliveryReportEvent $event) {
    $event->getReports();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[\Drupal\sms\Event\SmsEvents::ENTITY_PHONE_NUMBERS][] = ['myEntityPhoneNumbers'];
    $events[\Drupal\sms\Event\SmsEvents::MESSAGE_PRE_PROCESS][] = ['mySmsMessagePreprocess'];
    $events[\Drupal\sms\Event\SmsEvents::MESSAGE_POST_PROCESS][] = ['mySmsMessagePostprocess'];
    $events[\Drupal\sms\Event\SmsEvents::MESSAGE_GATEWAY][] = ['mySmsMessageGateway'];
    $events[\Drupal\sms\Event\SmsEvents::DELIVERY_REPORT_POST_PROCESS][] = ['myDeliveryReportPostProcessor'];
    return $events;
  }

}
