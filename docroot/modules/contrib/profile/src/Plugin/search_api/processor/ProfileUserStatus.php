<?php

namespace Drupal\profile\Plugin\search_api\processor;

use Drupal\profile\Entity\ProfileInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * Adds access checks for profiles.
 *
 * @SearchApiProcessor(
 *   id = "profile_user_status",
 *   label = @Translation("Profile user status"),
 *   description = @Translation("Adds a check to prevent profiles to be indexed when the owner is not active."),
 *   stages = {
 *     "preprocess_query" = 30,
 *   },
 * )
 */
class ProfileUserStatus extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    $supported_entity_types = ['profile'];
    foreach ($index->getDatasources() as $datasource) {
      if (in_array($datasource->getEntityTypeId(), $supported_entity_types)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterIndexedItems(array &$items) {
    foreach ($items as $item_id => $item) {
      $object = $item->getOriginalObject()->getValue();
      if ($object instanceof ProfileInterface) {
        $user = $object->getOwner();
        if (!$user->isActive()) {
          unset($items[$item_id]);
        }
      }
    }
  }

}
