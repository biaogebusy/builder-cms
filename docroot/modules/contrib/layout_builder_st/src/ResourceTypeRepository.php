<?php

namespace Drupal\layout_builder_st;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeRepository as CoreResourceTypeRepository;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;

/**
 * Prevents raw layout section data from being exposed via the HTTP API.
 */
class ResourceTypeRepository extends CoreResourceTypeRepository {

  /**
   * {@inheritdoc}
   */
  protected function createResourceType(EntityTypeInterface $entity_type, $bundle) {
    if ($entity_type->id() === 'entity_view_display') {
      // In order to prevent the raw layout section data from being exposed via
      // the HTTP API, we need to make sure that the entity type is using the
      // core entity class for this resource type, rather than our specialized
      // one. However, we only want this to be a momentary override; we do NOT
      // want it to persist in the entity type manager's cached entity type
      // definitions. So, to prevent that from happening, we clone the entity
      // type definition, modify it, and send the modified doppelgänger to the
      // parent method.
      $entity_type = clone $entity_type;
      $entity_type->setClass(LayoutBuilderEntityViewDisplay::class);
    }
    return parent::createResourceType($entity_type, $bundle);
  }

}
