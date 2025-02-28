<?php

/**
 * @file
 * Contains theme implementations.
 */

use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;

/**
 * Preprocess the entity bundles for listing.
 */
function template_preprocess_eck_content_add_list(&$variables) {
  $variables['types'] = [];
  if (!empty($variables['content'])) {
    foreach ($variables['content'] as $type) {
      $variables['types'][$type->type] = [
        'type' => $type->name,
        'add_link' => new Url('eck.entity.add', [
          'eck_entity_type' => $type->getEntityType()
            ->getBundleOf(),
          'eck_entity_bundle' => $type->type,
        ]),
        'description' => Xss::filterAdmin($type->description),
      ];
    }
  }
}
