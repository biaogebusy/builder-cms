<?php

namespace Drupal\eck;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;

/**
 * Entity View Builder for ECK entities.
 */
class EckEntityViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  protected function getBuildDefaults(EntityInterface $entity, $view_mode) {
    $build = parent::getBuildDefaults($entity, $view_mode);

    // Generalize the entity-type-specific defaults for easier default theming.
    $build['#theme'] = 'eck_entity';
    $build['#eck_entity'] = $entity;

    return $build;
  }

}
