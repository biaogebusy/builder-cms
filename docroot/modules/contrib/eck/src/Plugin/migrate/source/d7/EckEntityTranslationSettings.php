<?php

namespace Drupal\eck\Plugin\migrate\source\d7;

use Drupal\content_translation\Plugin\migrate\source\d7\EntityTranslationSettings;

/**
 * Drupal 7 ECK Entity Translation settings from variables.
 *
 * @MigrateSource(
 *   id = "d7_eck_entity_translation_settings",
 *   source_module = "entity_translation"
 * )
 */
class EckEntityTranslationSettings extends EntityTranslationSettings {

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator() {
    $results = array_map('unserialize', $this->prepareQuery()->execute()->fetchAllKeyed());
    $rows = [];

    // Find out which entity type uses entity translation by looking at the
    // 'entity_translation_entity_types' variable.
    $all_entity_types = array_filter($results['entity_translation_entity_types']);

    $eck_entity_types = $this->select('eck_entity_type', 'ecket')
      ->fields('ecket', ['name'])
      ->execute()
      ->fetchAllKeyed(0, 0);

    $entity_types = array_intersect_key($all_entity_types, $eck_entity_types);

    // If no entity type uses entity translation, there's nothing to do.
    if (empty($entity_types)) {
      return new \ArrayIterator($rows);
    }

    foreach ($entity_types as $entity_type) {
      foreach ($results as $name => $settings) {
        if (!preg_match('/^entity_translation_settings_' . $entity_type . '__(.+)$/', $name, $matches)) {
          continue;
        }
        // Retrieve bundle name from variable name.
        $bundle = str_replace("entity_translation_settings_{$entity_type}__", '', $name);

        $rows[] = [
          'id' => "{$entity_type}.{$bundle}",
          'target_entity_type_id' => $entity_type,
          'target_bundle' => $bundle,
          'default_langcode' => isset($settings['default_language']) ? $settings['default_language'] : 'und',
          'language_alterable' => isset($settings['hide_language_selector']) ? (bool) !$settings['hide_language_selector'] : TRUE,
          'untranslatable_fields_hide' => isset($settings['shared_fields_original_only']) ? (bool) $settings['shared_fields_original_only'] : FALSE,
        ];
      }
    }

    return new \ArrayIterator($rows);
  }


}
