<?php

namespace Drupal\viewsreference\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;

/**
 * Provides autocomplete selection for the ViewsReference field.
 *
 * Autocomplete selection for the ViewsReference field
 * with preselected options support.
 *
 * @EntityReferenceSelection(
 *   id = "viewsreference:view",
 *   label = @Translation("ViewsReference selection"),
 *   group = "views",
 *   weight = -1
 * )
 */
class ViewsReferenceViewSelection extends DefaultSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);
    $settings = $this->getConfiguration();
    if (!empty($settings['options']['preselected_list'])) {
      $query->condition('id', array_values($settings['options']['preselected_list']));
    }
    return $query;
  }

}
