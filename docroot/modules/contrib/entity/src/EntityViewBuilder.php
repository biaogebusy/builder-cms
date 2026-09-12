<?php

namespace Drupal\entity;

use Drupal\Core\Entity\EntityViewBuilder as CoreEntityViewBuilder;

@trigger_error('\Drupal\entity\EntityViewBuilder has been deprecated in favor of \Drupal\Core\Entity\EntityViewBuilder. Use that instead.');

/**
 * Provides a entity view builder with contextual links support.
 *
 * @deprecated in entity:8.x-1.0-beta3 and is removed from entity:2.0.0. Use \Drupal\Core\Entity\EntityViewBuilder instead.
 * @see https://www.drupal.org/node/2952495
 */
class EntityViewBuilder extends CoreEntityViewBuilder {

}
