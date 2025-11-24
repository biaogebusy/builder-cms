<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Views;

/**
 * Provides callbacks for our Views filters.
 *
 * @todo Convert this to a service when
 * https://www.drupal.org/project/drupal/issues/3533401 is fixed.
 */
class ViewsFilterOptions {

  /**
   * Provides the options for the remote website filter.
   *
   * @return array
   *   An array of labels keyed by entity ID.
   */
  public static function filterOptionsRemoteWebsite(): array {
    $labels = array_map(
      fn ($entity) => $entity->label(),
      \Drupal::service('entity_type.manager')->getStorage('remote')->loadMultiple(),
    );
    natcasesort($labels);
    return $labels;
  }

  /**
   * Provides the options for the channels filter.
   *
   * @return array
   *   An array of labels keyed by channel ID.
   */
  public static function filterOptionsChannel(): array {
    $cache = \Drupal::cache('data');

    // The RemoteManager populates this cache when the entity pull form is used.
    if ($cache_item = $cache->get('entity_share_client:channels')) {
      $channel_labels = array_merge(...array_values($cache_item->data));
    }
    else {
      // If there's been a cache clear that has lost our channel data, get the
      // IDs from the entity_import_status field values.
      $query = \Drupal::database()->select('entity_import_status', 'i')
        ->fields('i', ['channel_id'])
        ->groupBy('channel_id');
      $channel_ids = $query->execute()->fetchCol();
      $channel_labels = array_combine($channel_ids, $channel_ids);
    }

    return $channel_labels;
  }

  /**
   * Provides the options for the entity type filter.
   *
   * @return array
   *   An array of labels keyed by entity type ID.
   */
  public static function filterOptionsEntityTypeId(): array {
    $definitions = \Drupal::service('entity_type.manager')->getDefinitions();

    // Filter to content entity types.
    $definitions = array_filter(
      $definitions,
      fn ($definition) => $definition->getGroup() == 'content',
    );

    $labels = array_map(
      fn ($definition) => $definition->getLabel(),
      $definitions,
    );
    natcasesort($labels);
    return $labels;
  }

  /**
   * Provides the options for the entity bundle filter.
   *
   * @return array
   *   An array of labels keyed by bundle name. A bundle name that is repeated
   *   in different entity types will only appear once.
   */
  public static function filterOptionsBundle(): array {
    $all_bundle_info = \Drupal::service('entity_type.bundle.info')->getAllBundleInfo();
    $definitions = \Drupal::service('entity_type.manager')->getDefinitions();

    $labels = [];
    foreach ($all_bundle_info as $entity_type_id => $entity_type_bundle_info) {
      // Filter out non-content entity types.
      if ($definitions[$entity_type_id]->getGroup() != 'content') {
        continue;
      }

      foreach ($entity_type_bundle_info as $bundle => $bundle_info) {
        // This will clobber bundle names that are repeated in different entity
        // types, but those will have the same value in the data being filtered
        // anyway, so it's just the label that might be wrong.
        $labels[$bundle] = $bundle_info['label'];
      }
    }

    natcasesort($labels);
    return $labels;
  }

  /**
   * Provides the options for the policy filter.
   *
   * @return array
   *   An array of labels keyed by policy plugin ID.
   */
  public static function filterOptionsPolicy(): array {
    $labels = array_map(
      fn ($definition) => $definition['label'],
      \Drupal::service('plugin.manager.entity_share_client_policy')->getDefinitions(),
    );
    natcasesort($labels);
    return $labels;
  }

}

