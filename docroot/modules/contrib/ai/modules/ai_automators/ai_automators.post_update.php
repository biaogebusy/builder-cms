<?php

/**
 * @file
 * Contains post_update hooks for ai_automators module.
 */

use Drupal\ai_automators\AiAutomatorStatusField;
use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\field\FieldStorageConfigInterface;

/**
 * Adds enforced ai_automators dependencies to status field storage configs.
 */
function ai_automators_post_update_13001(&$sandbox) {
  \Drupal::state()->set('ai_automators.importing', TRUE);
  $config_entity_updater = \Drupal::classResolver(ConfigEntityUpdater::class);
  $callback = function (FieldStorageConfigInterface $field) {
    if ($field->getName() == AiAutomatorStatusField::FIELD_NAME) {
      $dependencies = $field->get('dependencies') ?? [];
      $modules = $dependencies['enforced']['module'] ?? [];
      if (in_array('ai_automators', $modules, TRUE)) {
        return FALSE;
      }
      $modules[] = 'ai_automators';
      sort($modules);
      $dependencies['enforced']['module'] = $modules;
      $field->set('dependencies', $dependencies);
      return TRUE;
    }
    return FALSE;
  };
  $config_entity_updater->update($sandbox, 'field_storage_config', $callback);
  \Drupal::state()->set('ai_automators.importing', FALSE);
}

/**
 * Adds enforced ai_automators dependencies to status field config entities.
 */
function ai_automators_post_update_13002(&$sandbox) {
  \Drupal::state()->set('ai_automators.importing', TRUE);
  $config_entity_updater = \Drupal::classResolver(ConfigEntityUpdater::class);
  $callback = function (FieldConfigInterface $field) {
    if ($field->getName() == AiAutomatorStatusField::FIELD_NAME) {
      $dependencies = $field->get('dependencies') ?? [];
      $modules = $dependencies['enforced']['module'] ?? [];
      if (in_array('ai_automators', $modules, TRUE)) {
        return FALSE;
      }
      $modules[] = 'ai_automators';
      sort($modules);
      $dependencies['enforced']['module'] = $modules;
      $field->set('dependencies', $dependencies);
      return TRUE;
    }
    return FALSE;
  };

  $config_entity_updater->update($sandbox, 'field_config', $callback);
  \Drupal::state()->set('ai_automators.importing', FALSE);
}
