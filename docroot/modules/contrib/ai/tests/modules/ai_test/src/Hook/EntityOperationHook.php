<?php

namespace Drupal\ai_test\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\ai_test\Entity\AIMockProviderResult;

/**
 * Hooks to interact with entity operations.
 */
class EntityOperationHook {

  use StringTranslationTrait;

  /**
   * Implements hook_entity_operation().
   */
  #[Hook('entity_operation')]
  public function echoEntityOperation(EntityInterface $entity) {
    return $entity instanceof AIMockProviderResult ? [
      'export' => [
        'title' => $this->t('Export to test'),
        'url' => Url::fromRoute('ai_test.export_file', [
          'result' => $entity->id(),
        ]),
        'weight' => 100,
      ],
    ] : [];
  }

}
