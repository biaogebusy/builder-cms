<?php

namespace Drupal\conflict;

final class ConflictTypes {

  /**
   * Indicates a change only on the remote version.
   */
  const CONFLICT_TYPE_REMOTE = 'conflict_remote';

  /**
   * Indicates a different change both on the local and remote version.
   */
  const CONFLICT_TYPE_LOCAL_REMOTE = 'conflict_local_remote';

}
