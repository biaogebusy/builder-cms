<?php

namespace Drupal\workspace\Plugin\Derivative;

use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;

/**
 * Provides contextual link definitions for all entity bundles.
 */
class RevisionsContextual extends RevisionsLocalTask implements ContainerDeriverInterface {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [];

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($entity_type->hasLinkTemplate('version-tree')) {
        $this->derivatives[$entity_type_id] = [
          'route_name' => "entity.$entity_type_id.version_tree",
          'title' => $this->t('Tree'),
          'group' => $entity_type_id,
          'weight' => 20,
        ];
      }
    }

    foreach ($this->derivatives as &$derivative) {
      $derivative += $base_plugin_definition;
    }

    return $this->derivatives;
  }

}
