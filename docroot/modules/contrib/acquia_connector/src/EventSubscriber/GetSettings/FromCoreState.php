<?php

namespace Drupal\acquia_connector\EventSubscriber\GetSettings;

use Drupal\acquia_connector\AcquiaConnectorEvents;
use Drupal\acquia_connector\Event\AcquiaSubscriptionSettingsEvent;
use Drupal\acquia_connector\Settings;
use Drupal\Core\State\StateInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Gets the ContentHub Server settings from configuration.
 */
class FromCoreState implements EventSubscriberInterface {

  /**
   * The Config Factory.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Get Settings From Core State constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaConnectorEvents::GET_SETTINGS][] = ['onGetSettings', 0];
    return $events;
  }

  /**
   * Extract settings from configuration and create a Settings object.
   *
   * @param \Drupal\acquia_connector\Event\AcquiaSubscriptionSettingsEvent $event
   *   The dispatched event.
   *
   * @see \Drupal\acquia_connector\Settings
   */
  public function onGetSettings(AcquiaSubscriptionSettingsEvent $event) {
    $state = $this->state->getMultiple([
      'acquia_connector.key',
      'acquia_connector.identifier',
      'acquia_connector.application_uuid',
    ]);

    $settings = new Settings(
      $event->getConfig(),
      $state['acquia_connector.identifier'] ?? '',
      $state['acquia_connector.key'] ?? '',
      $state['acquia_connector.application_uuid'] ?? '',
    );

    $settings->setReadOnly(FALSE);
    $event->setSettings($settings);
    $event->setProvider('core_state');
    // @phpstan-ignore-next-line
    $event->stopPropagation();
  }

}
