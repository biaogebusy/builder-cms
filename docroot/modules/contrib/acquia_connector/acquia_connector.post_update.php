<?php

/**
 * @file
 * Connector updates once other modules have made their own updates.
 */

use Drupal\Core\Extension\ModuleUninstallValidatorException;

/**
 * Migrate acquia telemetry settings to connector.
 */
function acquia_connector_post_update_migrate_acquia_telemetry() {
  if (\Drupal::moduleHandler()->moduleExists('acquia_telemetry')) {
    $debug = \Drupal::state()->get('acquia_telemetry.loud');
    if ($debug) {
      \Drupal::state()->set('acquia_connector.telemetry.loud', TRUE);
    }
    /** @var \Drupal\Core\Extension\ModuleInstallerInterface $module_installer */
    $module_installer = \Drupal::service('module_installer');
    try {
      $module_installer->uninstall(['acquia_telemetry'], FALSE);
    }
    catch (ModuleUninstallValidatorException $e) {
      // Do nothing, versions of acquia_cms_common and lightning_core declared
      // acquia_telemetry as a dependency, and we cannot automatically uninstall
      // the module.
    }
  }
}

/**
 * Ensure old Amplitude API key is removed from config.
 */
function acquia_connector_post_update_remove_amplitude_keys() {
  $acquia_connector_config = \Drupal::configFactory()->getEditable('acquia_connector.settings');
  if ($acquia_connector_config->get('spi.amplitude_api_key')) {
    $acquia_connector_config->clear('spi.amplitude_api_key');
    // Anything left in SPI should be in state, not config.
    $acquia_connector_config->clear('spi');
    $acquia_connector_config->save();
  }
}

/**
 * Rebuild a simple acquia connector config object.
 */
function acquia_connector_post_update_deprecated_variables() {
  $acquia_connector_config = \Drupal::configFactory()->getEditable('acquia_connector.settings');

  $variables = [
    'debug',
    'cron_interval',
    'cron_interval_override',
    'hide_signup_messages',
    'third_party_settings',
  ];
  $data = [];
  foreach ($variables as $var) {
    $data[$var] = $acquia_connector_config->get($var);
  }
  $acquia_connector_config->setData($data);
  $acquia_connector_config->save();

  // Migrate any existing subscription data from v3 to the new location.
  if ($acquia_subscription_data = \Drupal::state()->get('acquia_subscription_data')) {
    \Drupal::state()->delete('acquia_subscription_data');
    \Drupal::state()->set('acquia_connector.subscription_data', $acquia_subscription_data);
  }
  // Get subscription data from V4 location, and set uuid properly.
  $acquia_subscription_data = \Drupal::state()->get('acquia_connector.subscription_data');
  \Drupal::state()->set('acquia_connector.application_uuid', $acquia_subscription_data['uuid']);

  // Flush caches when upgrading from 3.0.x to 4.0.x.
  drupal_flush_all_caches();
}
