<?php

namespace Drupal\acquia_connector\EventSubscriber\GetSettings;

use Drupal\acquia_connector\AcquiaConnectorEvents;
use Drupal\acquia_connector\Event\AcquiaSubscriptionSettingsEvent;
use Drupal\acquia_connector\Settings;
use Drupal\Core\Site\Settings as CoreSettings;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Gets the Acquia Connector Server settings from Drupal's settings.
 */
class FromCoreSettings implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaConnectorEvents::GET_SETTINGS][] = ['onGetSettings', 50];
    return $events;
  }

  /**
   * Gets a prebuilt Settings object from Drupal's settings file.
   *
   * @param \Drupal\acquia_connector\Event\AcquiaSubscriptionSettingsEvent $event
   *   The dispatched event.
   *
   * @see \Drupal\acquia_connector\Settings
   */
  public function onGetSettings(AcquiaSubscriptionSettingsEvent $event) {
    $network_id = CoreSettings::get('ah_network_identifier', '');
    $network_key = CoreSettings::get('ah_network_key', '');
    $app_uuid = CoreSettings::get('ah_application_uuid', '');
    if ($network_id !== '' && $network_key !== '' && $app_uuid !== '') {
      $settings = new Settings($event->getConfig(), $network_id, $network_key, $app_uuid);
      $event->setSettings($settings);
      $event->setProvider('core_settings');
      // @phpstan-ignore-next-line
      $event->stopPropagation();
    }
  }

}
