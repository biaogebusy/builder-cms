<?php

namespace Drupal\workspace\Event;

/**
 * Replication events.
 */
final class ReplicationEvents {

  /**
   * Event fired when a replication has been queued.
   *
   * @var string
   */
  const QUEUED_REPLICATION = 'workspace.queued_replication';

  /**
   * Event fired before a replication begins.
   *
   * @var string
   */
  const PRE_REPLICATION = 'workspace.pre_replication';

  /**
   * Event fired after a replication is completed.
   *
   * This event is fired regardless of whether the replication succeeded or
   * failed.
   *
   * @var string
   */
  const POST_REPLICATION = 'workspace.post_replication';

}
