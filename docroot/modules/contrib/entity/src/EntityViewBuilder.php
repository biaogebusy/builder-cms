<?php

namespace Drupal\entity;

use Drupal\Core\Entity\EntityViewBuilder as CoreEntityViewBuilder;

@trigger_error('\Drupal\entity\EntityViewBuilder has been deprecated in favor of \Drupal\Core\Entity\EntityViewBuilder. Use that instead.');

/**
 * Provides a entity view builder with contextual links support.
 *
 * @deprecated in favor of \Drupal\Core\Entity\EntityViewBuilder. Use that
 *   instead.
 */
class EntityViewBuilder extends CoreEntityViewBuilder {

}
