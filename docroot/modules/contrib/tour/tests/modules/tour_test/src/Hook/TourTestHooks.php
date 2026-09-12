<?php

declare(strict_types=1);

namespace Drupal\tour_test\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for tour_test module.
 */
class TourTestHooks {

  /**
   * Implements hook_ENTITY_TYPE_load() for tour.
   */
  #[Hook('tour_load')]
  public function tourLoad(array $entities): void {
    if (isset($entities['tour-entity-create-test-en'])) {
      $entities['tour-entity-create-test-en']->loaded = 'Load hooks work';
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_presave() for tour.
   */
  #[Hook('tour_presave')]
  public function tourPresave(EntityInterface $entity): void {
    if ($entity->id() == 'tour-entity-create-test-en') {
      $entity->set('label', $entity->label() . ' alter');
    }
  }

  /**
   * Implements hook_tour_tips_alter().
   */
  #[Hook('tour_tips_alter')]
  public function tourTipsAlter(array &$tour_tips, EntityInterface $entity): void {
    foreach ($tour_tips as $tour_tip) {
      if ($tour_tip->get('id') == 'tour-code-test-1') {
        $tour_tip->set('body', 'Altered by hook_tour_tips_alter');
      }
    }
  }

}
