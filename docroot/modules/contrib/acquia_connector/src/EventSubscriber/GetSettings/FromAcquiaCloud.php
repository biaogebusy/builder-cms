<?php

namespace Drupal\acquia_connector\EventSubscriber\GetSettings;

use Drupal\acquia_connector\AcquiaConnectorEvents;
use Drupal\acquia_connector\Event\AcquiaSubscriptionSettingsEvent;
use Drupal\acquia_connector\Settings;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Site\Settings as CoreSettings;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Gets the ContentHub Server settings from environment variable.
 */
class FromAcquiaCloud implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Array containing the necessary environment variable keys.
   */
  const ENVIRONMENT_VARIABLES = [
    'AH_SITE_ENVIRONMENT',
    'AH_SITE_NAME',
    'AH_SITE_GROUP',
    'AH_APPLICATION_UUID',
  ];

  /**
   * Acquia Connector logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Drupal messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * State Service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructor for getting settings from Acquia Cloud.
   *
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   Acquia Connector logger channel.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Drupal messenger interface.
   * @param \Drupal\Core\State\StateInterface $state
   *   Drupal State.
   */
  public function __construct(LoggerChannelInterface $logger, MessengerInterface $messenger, StateInterface $state) {
    $this->logger = $logger;
    $this->messenger = $messenger;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaConnectorEvents::GET_SETTINGS][] = ['onGetSettings', 100];
    return $events;
  }

  /**
   * Extract settings from environment and create a Settings object.
   *
   * @param \Drupal\acquia_connector\Event\AcquiaSubscriptionSettingsEvent $event
   *   The dispatched event.
   *
   * @see \Acquia\ContentHubClient\Settings
   */
  public function onGetSettings(AcquiaSubscriptionSettingsEvent $event) {
    $metadata = [];
    foreach (self::ENVIRONMENT_VARIABLES as $var) {
      if (!empty(getenv($var))) {
        $metadata[$var] = getenv($var);
      }
    }

    // If the expected Acquia cloud environment variables are missing, return.
    if (count($metadata) !== count(self::ENVIRONMENT_VARIABLES)) {
      return;
    }

    // If the default network identifier settings are missing, return.
    global $config;
    if ((!CoreSettings::get('ah_network_identifier') && !isset($config['ah_network_identifier'])) ||
      (!CoreSettings::get('ah_network_key') && !isset($config['ah_network_key']))) {
      return;
    }

    // Store the default Cloud settings in the metadata storage.
    $metadata['ah_network_identifier'] = CoreSettings::get('ah_network_identifier') ?? $config['ah_network_identifier'];
    $metadata['ah_network_key'] = CoreSettings::get('ah_network_key') ?? $config['ah_network_key'];

    // Use the state service since customers can override subscription data.
    $state = $this->state->getMultiple([
      'acquia_connector.key',
      'acquia_connector.identifier',
      'acquia_connector.application_uuid',
      'spi.site_name',
      'spi.site_machine_name',
    ]);

    $settings = new Settings(
      $event->getConfig(),
      $state['acquia_connector.identifier'] ?? $metadata['ah_network_identifier'],
      $state['acquia_connector.key'] ?? $metadata['ah_network_key'],
      $state['acquia_connector.application_uuid'] ?? $metadata['AH_APPLICATION_UUID'],
      $metadata
    );

    $event->setProvider('acquia_cloud');
    $event->setSettings($settings);
    // @phpstan-ignore-next-line
    $event->stopPropagation();
  }

}
