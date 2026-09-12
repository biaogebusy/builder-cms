<?php

namespace Drupal\entity\Entity;

use Drupal\Core\Entity\RevisionableEntityBundleInterface as CoreRevisionableEntityBundleInterface;

@trigger_error('\Drupal\entity\Entity\RevisionableEntityBundleInterface has been deprecated in favor of \Drupal\Core\Entity\RevisionableEntityBundleInterface. Use that instead.');

/**
 * No longer a thing.
 *
 * @deprecated in entity:8.x-1.7 and is removed from entity:2.0.0.
 *   Use \Drupal\Core\Entity\RevisionableEntityBundleInterface instead.
 * @see https://www.drupal.org/node/2997467
 */
interface RevisionableEntityBundleInterface extends CoreRevisionableEntityBundleInterface {
}
