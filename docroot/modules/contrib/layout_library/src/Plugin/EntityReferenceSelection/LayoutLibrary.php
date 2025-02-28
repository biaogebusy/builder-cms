<?php

namespace Drupal\layout_library\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;

/**
 * Filters reference-able layouts according to target entity type and bundle.
 *
 * @EntityReferenceSelection(
 *   id = "layout_library",
 *   label = @Translation("Layout library"),
 *   group = "layout_library",
 *   weight = 0,
 *   entity_types = {"layout"}
 * )
 */
class LayoutLibrary extends DefaultSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);

    $configuration = $this->getConfiguration();
    if ($configuration['entity'] instanceof EntityInterface) {
      $query
        ->condition('targetEntityType', $configuration['entity']->getEntityTypeId())
        ->condition('targetBundle', $configuration['entity']->bundle());
    }
    return $query;
  }

}
