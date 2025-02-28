<?php

declare(strict_types=1);

namespace Drupal\forms_steps;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a Workflow entity.
 */
interface WorkflowInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
