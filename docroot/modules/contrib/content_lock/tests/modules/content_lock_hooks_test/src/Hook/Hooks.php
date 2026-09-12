<?php

declare(strict_types=1);

namespace Drupal\content_lock_hooks_test\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hooks for the content_lock_hooks_test module.
 */
class Hooks {

  /**
   * Implements hook_content_lock_entity_lockable().
   */
  #[Hook('content_lock_entity_lockable')]
  public function contentLockEntityLockable(EntityInterface $entity, array $config, ?string $form_op = NULL): bool {
    if ($entity->getEntityTypeId() === 'node' && $entity->bundle() === 'article' && (int) $entity->id() === 1) {
      return FALSE;
    }

    return TRUE;
  }

}
