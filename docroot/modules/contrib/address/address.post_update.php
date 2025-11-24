<?php

/**
 * @file
 * Contains post-update hooks for Address.
 */

declare(strict_types = 1);

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;

/**
 * Add the "Wrapper type" setting to the default widget.
 */
function address_post_update_default_widget_wrapper(?array &$sandbox = NULL): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'entity_form_display', function (EntityFormDisplayInterface $form_display): bool {
    $changed = FALSE;
    foreach ($form_display->getComponents() as $field => $component) {
      if (is_array($component)
        && array_key_exists('type', $component)
        && ($component['type'] === 'address_default')
        && !array_key_exists('wrapper_type', $component['settings'])) {
        $component['settings']['wrapper_type'] = 'details';
        $form_display->setComponent($field, $component);
        $changed = TRUE;
      }
    }

    return $changed;
  });
}
