<?php

/**
 * @file
 * Contains post update hooks for ai module.
 */

use Drupal\ai\Guardrail\AiGuardrailSetInterface;
use Drupal\Core\Config\Entity\ConfigEntityUpdater;

/**
 * Re-calculate dependencies for all guardrail sets.
 */
function ai_post_update_13001(&$sandbox) {
  // Resave all guardrail sets to re-calculate its dependencies.
  $config_entity_updater = \Drupal::classResolver(ConfigEntityUpdater::class);
  $callback = function (AiGuardrailSetInterface $guardrail_set) {
    return TRUE;
  };

  $config_entity_updater->update($sandbox, 'ai_guardrail_set', $callback);
}

/**
 * Add the global_guardrails key to ai.settings for existing installations.
 *
 * @see https://www.drupal.org/project/ai/issues/3584851
 */
function ai_post_update_14001() {
  $config = \Drupal::configFactory()->getEditable('ai.settings');
  if ($config->get('global_guardrails') === NULL) {
    $config->set('global_guardrails', [])->save(TRUE);
  }
}
