<?php

declare(strict_types=1);

namespace Drupal\content_lock\Access;

use Drupal\content_lock\ContentLock\ContentLockInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Symfony\Component\Routing\Route;

/**
 * Prevents access to routes if locking is not enabled for an entity type.
 */
class ContentLockViewsAccess implements AccessInterface {

  public function __construct(protected ContentLockInterface $contentLock) {
  }

  /**
   * Prevents access to routes if locking is not enabled for an entity type.
   */
  public function access(Route $route): AccessResultInterface {
    $entity_type_id = $route->getRequirement('_content_lock_enabled_access_checker');

    if ($entity_type_id === NULL) {
      return AccessResult::neutral();
    }

    $result = $this->contentLock->hasLockEnabled($entity_type_id) ?
      AccessResult::allowed() :
      AccessResult::forbidden('No content types are enabled for locking');
    return $result->addCacheTags(['config:content_lock.settings']);
  }

}
