<?php

declare(strict_types=1);

namespace Drupal\diff\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Views hooks for Diff module.
 */
class DiffViewsHooks {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Implements hook_views_data().
   */
  #[Hook('views_data')]
  public function viewsData(): array {
    $data = [];

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
      // Add the diff_from and diff_to fields to every revisionable entity type.
      if (!$entity_type->isRevisionable()) {
        continue;
      }
      $revision_base_table = $entity_type->getRevisionDataTable() ?? $entity_type->getRevisionTable();
      if ($revision_base_table === NULL) {
        continue;
      }

      $data[$revision_base_table]['diff_from'] = [
        'title' => $this->t('Diff from'),
        'help' => 'Diff "from" radio button to compare revisions. Also adds the "Compare" button.',
        'field' => [
          'id' => 'diff__from',
        ],
      ];
      $data[$revision_base_table]['diff_to'] = [
        'title' => $this->t('Diff to'),
        'help' => 'Diff "to" radio button to compare revisions.',
        'field' => [
          'id' => 'diff__to',
        ],
      ];
    }

    return $data;
  }

}
