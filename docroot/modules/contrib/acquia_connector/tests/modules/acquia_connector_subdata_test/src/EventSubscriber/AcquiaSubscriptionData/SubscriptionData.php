<?php

declare(strict_types=1);

namespace Drupal\acquia_connector_subdata_test\EventSubscriber\AcquiaSubscriptionData;

use Drupal\acquia_connector\AcquiaConnectorEvents;
use Drupal\acquia_connector\Event\AcquiaSubscriptionDataEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Test subscriber to populate extra subscription data.
 */
final class SubscriptionData implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      AcquiaConnectorEvents::GET_SUBSCRIPTION => ['onGetSubscriptionData', 100],
    ];
  }

  /**
   * Gets a prebuilt Settings object from Drupal's settings file.
   *
   * @param \Drupal\acquia_connector\Event\AcquiaSubscriptionDataEvent $event
   *   The dispatched event.
   */
  public function onGetSubscriptionData(AcquiaSubscriptionDataEvent $event) {
    $subscription_data = $event->getData();
    $product_data = [
      'foo' => 'bar',
      'data_from_subscription' => $subscription_data['uuid'],
    ];
    $event->setProductData('acquia_subdata_product', $product_data);
  }

}
