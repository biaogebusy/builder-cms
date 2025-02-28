<?php

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Implements hook_views_data().
 */
function entity_views_data() {
  $entity_types = \Drupal::entityTypeManager()->getDefinitions();
  $entity_types = array_filter($entity_types, function (EntityTypeInterface $entity_type) {
    return $entity_type->entityClassImplements(ContentEntityInterface::class);
  });

  $data = [];
  foreach ($entity_types as $entity_type) {
    /** @var \Drupal\Core\Entity\EntityTypeInterface $entity_type */
    if ($entity_type->isRevisionable()) {
      $entity_type_id = $entity_type->id();
      $revision_table = $entity_type->getRevisionDataTable() ?: $entity_type->getRevisionTable();

      if ($entity_type->hasLinkTemplate('revision')) {
        $data[$revision_table]['view_revision_' . $entity_type_id] = [
          'field' => [
            'title' => t('Link to revision'),
            'help' => t('Provide a simple link to the revision.'),
            'id' => 'entity_link_revision',
            'click sortable' => FALSE,
          ],
        ];
      }
      if ($entity_type->hasLinkTemplate('revision-revert-form')) {
        $data[$revision_table]['revert_revision_' . $entity_type_id] = [
          'field' => [
            'title' => t('Link to revert revision'),
            'help' => t('Provide a simple link to revert to the revision.'),
            'id' => 'entity_link_revision_revert',
            'click sortable' => FALSE,
          ],
        ];
      }
    }
  }

  return $data;
}
