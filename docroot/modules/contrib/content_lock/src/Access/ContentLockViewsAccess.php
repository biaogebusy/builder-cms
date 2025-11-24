<?php

declare(strict_types=1);

namespace Drupal\content_lock\Access;

use Drupal\content_lock\ContentLock\ContentLockInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Symfony\Component\Routing\Route;

class ContentLockViewsAccess implements AccessInterface {

  protected ContentLockInterface $content_lock;

  /**
   * Constructs a ContentLockViewsAccess object.
   *
   * @param \Drupal\content_lock\ContentLock\ContentLockInterface $content_lock
   *   The content lock service.
   */
  public function __construct(ContentLockInterface $content_lock) {
    $this->content_lock = $content_lock;
  }

  /**
   * {@inheritdoc}
   */
  public function access(Route $route): AccessResultInterface {
    $entity_type_id = $route->getRequirement('_content_lock_enabled_access_checker');

    if ($entity_type_id === NULL) {
      return AccessResult::neutral();
    }

    $result = $this->content_lock->hasLockEnabled($entity_type_id) ?
      AccessResult::allowed() :
      AccessResult::forbidden('No content types are enabled for locking');
    return $result->addCacheTags(['config:content_lock.settings']);
  }

}
