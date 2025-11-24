<?php

namespace Drupal\acquia_connector;

/**
 * Defines events for the acquia_connector module.
 *
 * @see \Drupal\acquia_connector\Event\AcquiaSubscriptionEvent
 */
final class AcquiaConnectorEvents {

  /**
   * The event fired to collect Acquia Cloud subscriptions.
   *
   * Acquia subscription keys can be provided in different ways. This event
   * allows modules to provide a Subscription object.
   *
   * @Event
   *
   * @see \Drupal\acquia_connector\Event\AcquiaSubscriptionEvent
   * @see \Drupal\acquia_connector\Client\ClientFactory::populateSubscription
   *
   * @var string
   */
  const GET_SETTINGS = 'acquia_connector_get_settings';

  /**
   * Event triggered when subscription data is about to be fetched.
   *
   * This event allows you to manipulate the data array that will be fetched
   * by modules using acquia connector's subscription data.
   */
  const GET_SUBSCRIPTION = 'acquia_connector_get_subscription';

  /**
   * Event triggered when building the connector settings form.
   *
   * Fetches global settings from individual acquia products.
   */
  const ACQUIA_PRODUCT_SETTINGS = 'acquia_connector_get_product_settings';

  /**
   * Event triggered when submitting the connector settings form.
   *
   * Updates form state values for individual acquia products.
   */
  const ALTER_PRODUCT_SETTINGS_SUBMIT = 'acquia_connector_alter_product_settings_submit';

}
