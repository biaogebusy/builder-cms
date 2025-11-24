<?php

namespace Drupal\eck;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeBundleInfo;

/**
 * Holds bundle info for eck entity types.
 */
class EckEntityTypeBundleInfo extends EntityTypeBundleInfo {

  /**
   * {@inheritdoc}
   */
  public function getAllBundleInfo() {
    if (empty($this->bundleInfo)) {
      $langCode = $this->languageManager->getCurrentLanguage()->getId();
      if ($cache = $this->cacheGet("eck_entity_bundle_info:$langCode")) {
        $this->bundleInfo = $cache->data;
      }
      else {
        $this->bundleInfo = $this->moduleHandler->invokeAll('entity_bundle_info');
        foreach ($this->entityTypeManager->getDefinitions() as $type => $entity_type) {
          if ($bundle_entity_type = $entity_type->getBundleEntityType()) {
            foreach ($this->entityTypeManager->getStorage($bundle_entity_type)
              ->loadMultiple() as $entity) {
              $this->bundleInfo[$type][$entity->id()]['label'] = $entity->label();
            }
          }
        }
        $this->moduleHandler->alter('entity_bundle_info', $this->bundleInfo);
        $this->cacheSet("eck_entity_bundle_info:$langCode", $this->bundleInfo, Cache::PERMANENT, [
          'entity_types',
          'entity_bundles',
        ]);
      }
    }

    return $this->bundleInfo;
  }

  /**
   * Determines if a given entity type has bundles.
   *
   * @param string $entity_type
   *   The entity type id.
   *
   * @return bool
   *   Does it have bundles?
   */
  public function entityTypeHasBundles($entity_type) {
    return !empty($this->getBundleInfo($entity_type));
  }

  /**
   * Retrieves the entity type bundle machine names.
   *
   * @param string $entity_type
   *   The entity type id.
   *
   * @return string[]
   *   The entity type bundle machine names.
   */
  public function getEntityTypeBundleMachineNames($entity_type) {
    return array_keys($this->getBundleInfo($entity_type));
  }

  /**
   * The entity type bundle count.
   *
   * @param string $entity_type
   *   The entity type id.
   *
   * @return int
   *   The number of bundles for the given entity type.
   */
  public function entityTypeBundleCount($entity_type) {
    return \count($this->getBundleInfo($entity_type));
  }

}
