<?php

declare(strict_types=1);

namespace Drupal\content_lock_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hooks for the content_lock_test module.
 */
class Hooks {

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(&$entity_types): void {
    $entity_types['entity_test_mul_changed']
      ->setLinkTemplate('compact', '/entity_test_mul_changed/manage/{entity_test_mul_changed}/compact')
      ->setFormClass('compact', 'Drupal\entity_test\EntityTestForm');
  }

}
