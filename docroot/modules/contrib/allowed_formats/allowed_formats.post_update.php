<?php

/**
 * @file
 * Post update functions for Allowed Formats module.
 */

use Drupal\field\Entity\FieldConfig;

/**
 * Updates existing configuration to store allowed_formats as sequence.
 */
function allowed_formats_post_update_store_allowed_formats_as_sequence() {
  foreach (FieldConfig::loadMultiple() as $field_config) {
    /** @var \Drupal\field\Entity\FieldConfig $field_config */
    if (in_array($field_config->getType(), _allowed_formats_field_types(), TRUE)) {
      $allowed_formats = $field_config->getThirdPartySettings('allowed_formats');
      if (!empty($allowed_formats)) {
        // Don't do anything if the configuration is already a sequence.
        if (isset($allowed_formats['allowed_formats']) && is_array($allowed_formats['allowed_formats'])) {
          continue;
        }
        // Unset existing configuration.
        foreach ($allowed_formats as $key => $value) {
          $field_config->unsetThirdPartySetting('allowed_formats', $key);
        }
        $field_config->setThirdPartySetting('allowed_formats', 'allowed_formats', array_values(array_filter($allowed_formats)));
        $field_config->save();
      }
    }
  }

  return t('Allowed formats in field configuration has been updated.');
}
