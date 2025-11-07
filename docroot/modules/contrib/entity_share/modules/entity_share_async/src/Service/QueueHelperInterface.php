<?php

declare(strict_types = 1);

namespace Drupal\entity_share_async\Service;

/**
 * Queue helper interface methods.
 */
interface QueueHelperInterface {

  /**
   * The queue ID.
   */
  const QUEUE_NAME = 'entity_share_async_import';

  /**
   * The state ID.
   */
  const STATE_ID = 'entity_share_async.states';

  /**
   * Enqueue entity to be synced later.
   *
   * @param string $remote_id
   *   The remote ID.
   * @param string $channel_id
   *   The channel ID.
   * @param string $import_config_id
   *   The import config ID.
   * @param string[] $uuids
   *   The UUIDs of the entities to pull.
   */
  public function enqueue($remote_id, $channel_id, $import_config_id, array $uuids);

}
