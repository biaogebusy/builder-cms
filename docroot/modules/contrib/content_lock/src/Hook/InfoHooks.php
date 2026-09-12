<?php

namespace Drupal\content_lock\Hook;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Info hooks for the Content Lock module.
 *
 * These hooks implementations have no dependencies and this class exists as
 * using ContentLookHooks would result in circular dependencies.
 */
class InfoHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'help.page.content_lock':
        $output = '';
        $output .= '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('The Content Lock module prevents multiple users from trying to edit a single node simultaneously to prevent edit conflicts.') . '</p>';
        return $output;
    }
    return NULL;
  }

  /**
   * Implements hook_entity_type_build().
   */
  #[Hook('entity_type_build')]
  public function entityTypeBuild(array &$entity_types): void {
    foreach ($entity_types as &$entity_type) {
      if ($entity_type instanceof ContentEntityTypeInterface) {
        if (!$entity_type->hasHandlerClass('break_lock_form')) {
          $entity_type->setHandlerClass('break_lock_form', '\Drupal\content_lock\Form\EntityBreakLockForm');
        }
      }
    }
  }

}
