<?php

namespace Drupal\entity\Revision;

use Drupal\Core\Entity\RevisionableContentEntityBase as BaseRevisionableContentEntityBase;

/**
 * Improves the url route handling of core's revisionable content entity base.
 */
abstract class RevisionableContentEntityBase extends BaseRevisionableContentEntityBase {

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);

    if (strpos($this->getEntityType()->getLinkTemplate($rel), $this->getEntityTypeId() . '_revision') !== FALSE) {
      $uri_route_parameters[$this->getEntityTypeId() . '_revision'] = $this->getRevisionId();
    }

    return $uri_route_parameters;
  }

}
