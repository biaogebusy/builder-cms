<?php

namespace Drupal\workspace\Plugin\EntityReferenceSelection;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\multiversion\Entity\WorkspaceInterface;

/**
 * Provides specific access control for the node entity type.
 *
 * @EntityReferenceSelection(
 *   id = "default:workspace_pointer",
 *   label = @Translation("Workspace pointer selection"),
 *   entity_types = {"workspace_pointer"},
 *   group = "default",
 *   weight = 1
 * )
 */
class WorkspacePointerSelection extends DefaultSelection {

  /**
   * {@inheritdoc}
   */
  public function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);
    if (isset($this->getConfiguration()['entity']) && $this->getConfiguration()['entity'] instanceof WorkspaceInterface && !$this->getConfiguration()['entity']->isNew()) {
      $group = $query->orConditionGroup()
        ->condition('workspace_pointer', $this->getConfiguration()['entity']->id(), '<>')
        ->notExists('workspace_pointer');
      $query->condition($group);
    }
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    $target_type = $this->getConfiguration()['target_type'];

    $query = $this->buildEntityQuery($match, $match_operator);
    if ($limit > 0) {
      $query->range(0, $limit);
    }

    $result = $query->execute();

    if (empty($result)) {
      return [];
    }

    $options = [];
    $entities = $this->entityManager->getStorage($target_type)->loadMultiple($result);
    foreach ($entities as $entity_id => $entity) {
      /** @var WorkspaceInterface $workspace */
      if ($workspace = $entity->getWorkspace()) {
        if (!$workspace->isPublished() || $workspace->getQueuedForDelete()) {
          continue;
        }
      }
      $bundle = $entity->bundle();
      $options[$bundle][$entity_id] = Html::escape($this->entityManager->getTranslationFromContext($entity)->label());
    }

    return $options;
  }

}
