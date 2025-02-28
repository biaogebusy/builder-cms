<?php

namespace Drupal\entity\Plugin\views\field;

use Drupal\views\Plugin\views\field\EntityLink;

/**
 * Field handler to present a link to an entity revision.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("entity_link_revision")
 */
class EntityLinkRevision extends EntityLink {

  /**
   * {@inheritdoc}
   */
  protected function getEntityLinkTemplate() {
    return 'revision';
  }

}
