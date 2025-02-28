<?php

/**
 * @file
 * Post update hooks.
 */

use Drupal\entity_print\Entity\PrintEngineStorage;

/**
 * Sets the new default configuration for dompdf.
 */
function entity_print_post_update_new_dompdf_configuration() {
  /** @var \Drupal\entity_print\Entity\PrintEngineStorage $engine_config */
  if ($engine_config = PrintEngineStorage::load('dompdf')) {
    $settings = $engine_config->getSettings();
    $settings['default_paper_size'] = 'letter';
    $engine_config->setSettings($settings);
    $engine_config->save();
  }
}

/**
 * Migrate simple config into new config entities.
 */
function entity_print_post_update_migrate_config() {
  $config = \Drupal::configFactory()->getEditable('entity_print.settings');
  if ($plugin_id = $config->get('print_engine')) {
    /** @var \Drupal\entity_print\Plugin\EntityPrintPluginManagerInterface $plugin_manager */
    $plugin_manager = \Drupal::service('plugin.manager.entity_print.print_engine');
    $definition = $plugin_manager->getDefinition($plugin_id);
    /** @var \Drupal\entity_print\Plugin\PrintEngineInterface $class */
    $class = $definition['class'];

    if ($class::dependenciesAvailable()) {
      $values = [
        'id' => $plugin_id,
        'settings' => [],
      ];

      // If we have a binary location then add it.
      if ($binary_location = $config->get('binary_location')) {
        $values['settings']['binary_location'] = $binary_location;
      }
      // Create the new config entity.
      \Drupal::entityTypeManager()
        ->getStorage('print_engine')
        ->create($values)
        ->save();

      // Make sure to remove the binary location.
      $config->clear('binary_location');
      $config->save();
    }
  }

  return t('All configuration upgraded');
}
