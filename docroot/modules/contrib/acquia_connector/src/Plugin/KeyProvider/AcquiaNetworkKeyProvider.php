<?php

namespace Drupal\acquia_connector\Plugin\KeyProvider;

use Drupal\acquia_connector\Traits\UtilityTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings as CoreSettings;
use Drupal\key\Plugin\KeyProviderBase;
use Drupal\key\Plugin\KeyPluginFormInterface;
use Drupal\key\KeyInterface;

/**
 * Adds Acquia Cloud key provider for network settings.
 *
 * @KeyProvider(
 *   id = "acquia_cloud_network",
 *   label = @Translation("Acquia Cloud (Legacy Keys)"),
 *   description = @Translation("The Acquia Cloud Network key provider fetches legacy keys when running on Acquia hosting."),
 *   tags = {
 *     "acquia",
 *   },
 *   key_value = {
 *     "accepted" = FALSE,
 *     "required" = FALSE
 *   }
 * )
 */
class AcquiaNetworkKeyProvider extends KeyProviderBase implements KeyPluginFormInterface {

  use UtilityTrait;

  /**
   * Array containing the necessary environment variable keys.
   */
  const ENVIRONMENT_VARIABLES = [
    'AH_APPLICATION_UUID',
    'AH_SITE_ENVIRONMENT',
    'AH_SITE_GROUP',
    'AH_SITE_NAME',
  ];

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['info'] = [
      '#type' => 'markup',
      '#markup' => $this->t('This key provider automatically fetches Acquia network credentials when running on Acquia hosting. No configuration is required.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // No validation needed.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // No submission handling needed.
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyValue(KeyInterface $key) {
    // Check if we're running on Acquia hosting by verifying environment variables.
    if (!$this->isOnAcquiaHosting()) {
      return NULL;
    }

    // Get metadata from environment variables.
    $metadata = $this->getEnvironmentInformation(self::ENVIRONMENT_VARIABLES);

    // If the expected Acquia cloud environment variables are missing, return NULL.
    if (count($metadata) !== count(self::ENVIRONMENT_VARIABLES)) {
      return NULL;
    }

    // Skip IDE and ODE environments.
    $environment = $metadata['AH_SITE_ENVIRONMENT'] ?? '';
    if (in_array($environment, ['ide', 'ode'])) {
      return NULL;
    }

    // If the default network identifier settings are missing, return NULL.
    global $config;
    if ((!CoreSettings::get('ah_network_identifier') && !isset($config['ah_network_identifier'])) ||
      (!CoreSettings::get('ah_network_key') && !isset($config['ah_network_key']))) {
      return NULL;
    }

    // Get settings from CoreSettings.
    $network_id = CoreSettings::get('ah_network_identifier') ?? $config['ah_network_identifier'] ?? '';
    $network_key = CoreSettings::get('ah_network_key') ?? $config['ah_network_key'] ?? '';
    $app_uuid = $metadata['AH_APPLICATION_UUID'] ?? '';

    // Return the credentials array.
    return [
      'ah_network_identifier' => $network_id,
      'ah_network_key' => $network_key,
      'ah_application_uuid' => $app_uuid,
    ];
  }

  /**
   * Check if we're running on Acquia hosting.
   *
   * @return bool
   *   TRUE if running on Acquia hosting, FALSE otherwise.
   */
  protected function isOnAcquiaHosting(): bool {
    // Check for all required Acquia environment variables.
    foreach (self::ENVIRONMENT_VARIABLES as $var) {
      if (empty(getenv($var))) {
        return FALSE;
      }
    }
    return TRUE;
  }

}
