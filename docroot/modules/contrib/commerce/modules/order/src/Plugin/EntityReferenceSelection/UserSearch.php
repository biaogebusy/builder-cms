<?php

namespace Drupal\commerce_order\Plugin\EntityReferenceSelection;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\Plugin\EntityReferenceSelection\UserSelection;

/**
 * Allows user selection by name or email address.
 */
#[EntityReferenceSelection(
  id: "commerce:user",
  label: new TranslatableMarkup('User selection by name or email'),
  group: 'commerce',
  weight: 2,
  entity_types: ['user'],
)]
class UserSearch extends UserSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = DefaultSelection::buildEntityQuery($match, $match_operator);
    $configuration = $this->getConfiguration();
    // Filter out the Anonymous user if the selection handler is configured to
    // exclude it.
    if (!$configuration['include_anonymous']) {
      $query->condition('uid', 0, '<>');
    }

    // The user entity doesn't have a label column.
    if (isset($match)) {
      $group = $query->orConditionGroup()
        ->condition('name', $match, $match_operator)
        ->condition('mail', $match, $match_operator);
      $query->condition($group);
    }

    // Filter by role.
    if (!empty($configuration['filter']['role'])) {
      $query->condition('roles', $configuration['filter']['role'], 'IN');
    }

    // Adding the permission check is sadly insufficient for users: core
    // requires us to also know about the concept of 'blocked' and 'active'.
    if (!$this->currentUser->hasPermission('administer users')) {
      $query->condition('status', 1);
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
    $entities = $this->entityTypeManager->getStorage($target_type)->loadMultiple($result);
    foreach ($entities as $entity_id => $entity) {
      $bundle = $entity->bundle();
      $label = $entity->getAccountName() . ' <' . $entity->getEmail() . '>';
      $options[$bundle][$entity_id] = Html::escape($label);
    }

    return $options;
  }

}
