<?php

declare(strict_types=1);

namespace Drupal\acquia_connector\EventSubscriber;

use Drupal\acquia_connector\Subscription;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to refresh subscription data when Connector settings change.
 */
final class ConfigSubscriber implements EventSubscriberInterface {

  /**
   * The subscription.
   *
   * @var \Drupal\acquia_connector\Subscription
   */
  private $subscription;

  /**
   * Constructs a new ConfigSubscriber object.
   *
   * @param \Drupal\acquia_connector\Subscription $subscription
   *   The subscription.
   */
  public function __construct(Subscription $subscription) {
    $this->subscription = $subscription;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ConfigEvents::SAVE => 'onSave',
    ];
  }

  /**
   * Config save event handler.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The event.
   */
  public function onSave(ConfigCrudEvent $event) {
    if ($event->getConfig()->getName() === 'acquia_connector.settings'
      && $event->isChanged('third_party_settings')
    ) {
      $this->subscription->getSubscription(TRUE);
    }
  }

}
