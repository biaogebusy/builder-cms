<?php

namespace Drupal\entity\Entity;

use Drupal\Core\Entity\RevisionableEntityBundleInterface as CoreRevisionableEntityBundleInterface;

@trigger_error('\Drupal\entity\Entity\RevisionableEntityBundleInterface has been deprecated in favor of \Drupal\Core\Entity\RevisionableEntityBundleInterface. Use that instead.');

/**
 * @deprecated in favor of
 *   \Drupal\Core\Entity\RevisionableEntityBundleInterface. Use that instead.
 */
interface RevisionableEntityBundleInterface extends CoreRevisionableEntityBundleInterface {
}
