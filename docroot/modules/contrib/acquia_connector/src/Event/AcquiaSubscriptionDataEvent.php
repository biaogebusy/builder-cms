<?php

namespace Drupal\acquia_connector\Event;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * The event dispatched to find settings for Acquia Connector.
 */
class AcquiaSubscriptionDataEvent extends EventBase {

  /**
   * Raw subscription data to alter.
   *
   * @var array
   */
  protected $subscriptionData;

  /**
   * Product Data to alter.
   *
   * @var array
   */
  protected $productData = [
    'view' => 'Acquia Network',
  ];

  /**
   * Config Factory for events to fetch their own configs.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Pass in connector config by default to all events.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Acquia Connector settings.
   * @param array $subscription_data
   *   Raw Subscription Data.
   */
  public function __construct(ConfigFactoryInterface $config_factory, array $subscription_data) {
    $this->configFactory = $config_factory;
    $this->subscriptionData = $subscription_data;
  }

  /**
   * Gets the Acquia Connector settings object.
   *
   * @return array
   *   The Acquia Subscription data.
   */
  public function getData() {
    return $this->subscriptionData;
  }

  /**
   * Gets product specific subscription data.
   *
   * @return array
   *   The Acquia Subscription data.
   */
  public function getProductData() {
    return $this->productData;
  }

  /**
   * Return static config for an event subscriber.
   *
   * @return \Drupal\Core\Config\Config
   *   The Config Object.
   */
  public function getConfig($config_settings) {
    return $this->configFactory->get($config_settings);
  }

  /**
   * Set the subscription data.
   *
   * Event subscribers to this event should be mindful to use the
   * NestedArray::mergeDeepArray() method to merge data together and not
   * overwrite other event subscriber's data.
   *
   * @param array $data
   *   Data to set.
   */
  public function setData(array $data): void {
    $this->subscriptionData = $data;
  }

  /**
   * Set Acquia Product Data.
   *
   * This event is preferable to use over the setData method, which overwrites
   * all data. This limits the scope of data to a specific product array key.
   *
   * @param string $product
   *   Acquia Product to set data to.
   * @param array $data
   *   Data to set.
   */
  public function setProductData(string $product, array $data): void {
    $this->productData[$product] = $data;
  }

}
