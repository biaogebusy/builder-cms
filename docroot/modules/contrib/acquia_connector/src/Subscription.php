<?php

namespace Drupal\acquia_connector;

use Drupal\acquia_connector\Client\ClientFactory;
use Drupal\acquia_connector\Event\AcquiaSubscriptionDataEvent;
use Drupal\acquia_connector\Event\AcquiaSubscriptionSettingsEvent;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Acquia Subscription service.
 *
 * The Acquia Subscription service is the public way other items can access
 * Acquia's services via connector. There is a settings object that is invoked
 * via an Event Subscriber, to fetch settings from envvars, settings.php or the
 * state system.
 *
 * Acquia Subscription data is always stored in state, and is not part of the
 * settings object.
 *
 * @package Drupal\acquia_connector.
 */
class Subscription {

  use StringTranslationTrait;

  /**
   * Errors defined by Acquia.
   */
  const NOT_FOUND = 1000;
  const KEY_MISMATCH = 1100;
  const EXPIRED = 1200;
  const REPLAY_ATTACK = 1300;
  const KEY_NOT_FOUND = 1400;
  const MESSAGE_FUTURE = 1500;
  const MESSAGE_EXPIRED = 1600;
  const MESSAGE_INVALID = 1700;
  const VALIDATION_ERROR = 1800;
  const PROVISION_ERROR = 9000;

  /**
   * Subscription message lifetime defined by Acquia.
   */
  // 15 * 60.
  const MESSAGE_LIFETIME = 900;

  /**
   * Event Dispatcher Service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * Settings Provider.
   *
   * @var string
   */
  protected $settingsProvider;

  /**
   * Settings object.
   *
   * @var \Drupal\acquia_connector\Settings
   */
  protected $settings;

  /**
   * Raw Acquia subscription data.
   *
   * @var array
   */
  protected $subscriptionData;

  /**
   * Connector Client.
   *
   * @var \Drupal\acquia_connector\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * Drupal config.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Drupal State Service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Acquia Subscription Constructor.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The event dispatcher.
   * @param \Drupal\acquia_connector\Client\ClientFactory $client_factory
   *   The acquia connector client factory.
   * @param \Drupal\Core\State\StateInterface $state
   *   Drupal State System.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config Factory.
   */
  public function __construct(EventDispatcherInterface $dispatcher, ClientFactory $client_factory, StateInterface $state, ConfigFactoryInterface $config_factory) {
    $this->dispatcher = $dispatcher;
    $this->state = $state;
    $this->clientFactory = $client_factory;
    $this->configFactory = $config_factory;
  }

  /**
   * Call the event to populate Acquia Connector settings.
   */
  public function populateSettings() {
    $event = new AcquiaSubscriptionSettingsEvent($this->configFactory);

    // @todo Remove after dropping support for Drupal 8.
    if (version_compare(\Drupal::VERSION, '9.1', '>=')) {
      $this->dispatcher->dispatch($event, AcquiaConnectorEvents::GET_SETTINGS);
    }
    else {
      // @phpstan-ignore-next-line
      $this->dispatcher->dispatch(AcquiaConnectorEvents::GET_SETTINGS, $event);
    }

    $this->settings = $event->getSettings();
    $this->settingsProvider = $event->getProvider();
  }

  /**
   * Retrieves the stored subscription.
   *
   * @return \Drupal\acquia_connector\Settings
   *   The Connector Settings Object.
   */
  public function getSettings() {
    if (!$this->settings || !$this->settings->getIdentifier()) {
      $this->populateSettings();
    }
    return $this->settings;
  }

  /**
   * Gets the subscription provider from the subscription event for settings.
   *
   * @return string
   *   The name of settings' provider.
   */
  public function getProvider() {
    if (!$this->settings || !$this->settings->getIdentifier()) {
      $this->populateSettings();
    }
    return $this->settingsProvider;
  }

  /**
   * Retrieve the Acquia Subscription.
   *
   * @return array
   *   The Raw Subscription Data.
   */
  public function getSubscription(?bool $refresh = NULL, array $body = []) {
    // If Settings do not exist, we have no subscription to fetch.
    if (!$this->hasCredentials()) {
      // Ensure subscription data is scrubbed.
      $this->state->delete('acquia_connector.subscription_data');
      Cache::invalidateTags(['acquia_connector_subscription']);
      return ['active' => FALSE];
    }
    // Used the cached data if refresh is NULL or FALSE.
    if (isset($this->subscriptionData) && $refresh !== TRUE) {
      return $this->subscriptionData;
    }
    $subscriptionData = $this->state->get('acquia_connector.subscription_data', []);
    // Handle edge cases where acquia_connector.subscription_data is set wrong.
    if (!is_array($subscriptionData)) {
      $subscriptionData = [];
    }
    if ($subscriptionData !== [] && $refresh !== TRUE) {
      // Ensure the legacy location of UUID is up-to-date.
      $subscriptionData['uuid'] = $this->getSettings()->getApplicationUuid();
      return $subscriptionData;
    }
    // If there is no local subscription data, retrieve it.
    $subscriptionData += $this->getDefaultSubscriptionData();
    try {
      $client = $this->clientFactory->getCloudApiClient();
      $application_response = $client->get('/api/applications/' . $this->getSettings()->getApplicationUuid());
      $application_data = Json::decode((string) $application_response->getBody());
      $subscription_uuid = $application_data['subscription']['uuid'];
      $subscription_response = $client->get('/api/subscriptions/' . $subscription_uuid);
      $subscription_info = Json::decode((string) $subscription_response->getBody());

      $subscriptionData['active'] = $subscription_info['flags']['active'];
      $subscriptionData['application'] = $application_data;
      $subscriptionData['subscription_name'] = $subscription_info['name'];
      // Expiration Date may have been a string, ensure its set as an array.
      $subscriptionData['expiration_date'] = [
        'value' => $subscription_info['expire_at'],
      ];
    }
    catch (RequestException | ConnectorException $e) {
    }

    // If subscription expiration date passed, set gratis value to TRUE.
    if (isset($subscriptionData['expiration_date']['value'])) {
      $subscriptionData['gratis'] = strtotime($subscriptionData['expiration_date']['value']) < strtotime(date("m/d/Y"));
    }
    // Allow other modules to add metadata to the subscription.
    $event = new AcquiaSubscriptionDataEvent($this->configFactory, $subscriptionData);
    // @todo Remove after dropping support for Drupal 8.
    if (version_compare(\Drupal::VERSION, '9.1', '>=')) {
      $this->dispatcher->dispatch($event, AcquiaConnectorEvents::GET_SUBSCRIPTION);
    }
    else {
      // @phpstan-ignore-next-line
      $this->dispatcher->dispatch(AcquiaConnectorEvents::GET_SUBSCRIPTION, $event);
    }
    // Get data from subscription event.
    $this->subscriptionData = $event->getData();

    // Get product data from subscription event.
    $this->subscriptionData['product'] = $event->getProductData();

    // Save subscription data to state.
    $this->state->set('acquia_connector.subscription_data', $this->subscriptionData);
    Cache::invalidateTags(['acquia_connector_subscription']);

    return $this->subscriptionData;
  }

  /**
   * Build a subscription data object to mimic legacy NSPI responses.
   *
   * @return array
   *   The subscription data.
   */
  private function getDefaultSubscriptionData() {
    if (!$this->hasCredentials()) {
      return ['active' => FALSE];
    }

    return [
      'active' => TRUE,
      'gratis' => FALSE,
      'href' => "",
      'uuid' => $this->getSettings()->getApplicationUuid(),
      'subscription_name' => "",
      "expiration_date" => "",
      "search_service_enabled" => 1,
    ];
  }

  /**
   * Delete any subscription data held in the database.
   */
  public function delete() {
    $this->state->set('acquia_connector.subscription_data', ['active' => FALSE]);
    $this->state->delete('spi.site_name');
    $this->state->delete('spi.site_machine_name');
  }

  /**
   * Helper function to check if an identifier and key exist.
   */
  public function hasCredentials() {
    $settings = $this->getSettings();
    return $settings->getIdentifier() && $settings->getSecretKey() && $settings->getApplicationUuid();
  }

  /**
   * Helper function to check if the site has an active subscription.
   */
  public function isActive() {
    $active = FALSE;
    // Subscription cannot be active if we have no credentials.
    if ($this->hasCredentials()) {
      $data = $this->state->get('acquia_connector.subscription_data');
      if ($data !== NULL) {
        if (is_array($data)) {
          return !empty($data['active']);
        }
      }
      // Only retrieve cached subscription at this time.
      $subscription = $this->getSubscription(FALSE);

      // If we don't have a timestamp, or timestamp is less than a day, fetch.
      if (!isset($subscription['timestamp']) || (isset($subscription['timestamp']) && (time() - $subscription['timestamp'] > 60 * 60 * 24))) {
        try {
          $subscription = $this->getSubscription(TRUE, ['no_heartbeat' => 1]);
          $this->state->set('acquia_connector.subscription_data', $subscription);
        }
        catch (ConnectorException $e) {
        }
      }
      $active = !empty($subscription['active']);
    }
    return $active;
  }

  /**
   * Return an error message by the error code.
   *
   * Returns an error message for the most recent (failed) attempt to connect
   * to the Acquia during the current page request. If there were no failed
   * attempts, returns FALSE.
   *
   * This function assumes that the most recent error came from the Acquia;
   * otherwise, it will not work correctly.
   *
   * @param int $errno
   *   Error code defined by the module.
   *
   * @return mixed
   *   The error message string or FALSE.
   */
  public function connectionErrorMessage($errno) {
    if ($errno) {
      switch ($errno) {
        case self::NOT_FOUND:
          return $this->t('The identifier you have provided does not exist at Acquia or is expired. Please make sure you have used the correct value and try again.');

        case self::EXPIRED:
          return $this->t('Your Acquia Subscription subscription has expired. Please renew your subscription so that you can resume using Acquia services.');

        case self::MESSAGE_FUTURE:
          return $this->t('Your server is unable to communicate with Acquia due to a problem with your clock settings. For security reasons, we reject messages that are more than @time ahead of the actual time recorded by our servers. Please fix the clock on your server and try again.', ['@time' => \Drupal::service('date.formatter')->formatInterval(Subscription::MESSAGE_LIFETIME)]);

        case self::MESSAGE_EXPIRED:
          return $this->t('Your server is unable to communicate with Acquia due to a problem with your clock settings. For security reasons, we reject messages that are more than @time older than the actual time recorded by our servers. Please fix the clock on your server and try again.', ['@time' => \Drupal::service('date.formatter')->formatInterval(Subscription::MESSAGE_LIFETIME)]);

        case self::VALIDATION_ERROR:
          return $this->t('The identifier and key you have provided for the Acquia Subscription do not match. Please make sure you have used the correct values and try again.');

        default:
          return $this->t('There is an error communicating with the Acquia Subscription at this time. Please check your identifier and key and try again.');
      }
    }
    return FALSE;
  }

}
