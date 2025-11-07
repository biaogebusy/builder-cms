<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Exception;

/**
 * Exception thrown when trying to import a non-existing entity type or bundle.
 */
class ResourceTypeNotFoundException extends \Exception {
}
