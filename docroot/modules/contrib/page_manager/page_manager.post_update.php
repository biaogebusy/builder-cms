<?php

/**
 * @file
 * Post update functions for page_manager module.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\page_manager\PageVariantInterface;

/**
 * Updating page variants with new current_user context name.
 */
function page_manager_post_update_replace_current_user_context(&$sandbox = NULL) {
  \Drupal::classResolver(ConfigEntityUpdater::class)
    ->update($sandbox, 'page_variant', function (PageVariantInterface $pageVariant) {
      $process_contexts = function (&$context_mapping) {
        foreach ($context_mapping as $type => $name) {
          if ($name == 'current_user') {
            $context_mapping[$type] = '@user.current_user_context:current_user';
          }
        }
      };

      $conditions = $pageVariant->get('selection_criteria');
    if (isset($conditions)) {
      foreach ($conditions as &$condition) {
        $process_contexts($condition['context_mapping']);
      }
      $pageVariant->set('selection_criteria', $conditions);
    }

      $variant_settings = $pageVariant->get('variant_settings');
    if (isset($variant_settings['blocks'])) {
      foreach ($variant_settings['blocks'] as &$block) {
        $process_contexts($block['context_mapping']);
      }
      $pageVariant->set('variant_settings', $variant_settings);
    }

      $pageVariant->save();
    });
}
