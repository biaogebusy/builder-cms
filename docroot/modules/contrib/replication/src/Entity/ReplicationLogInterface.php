<?php

namespace Drupal\replication\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

interface ReplicationLogInterface extends ContentEntityInterface {

  /**
   * Gets the entire history.
   *
   * @return array
   *   List of history values.
   */
  public function getHistory();

  /**
   * Sets the entire history.
   *
   * @param array $history
   *   List containing replication history items.
   *
   * @return $this
   */
  public function setHistory($history);

  /**
   * Gets the session id.
   *
   * @return string
   *   The session id.
   */
  public function getSessionId();

  /**
   * Sets the session id.
   *
   * @param string $session_id
   *   The session id to set.
   *
   * @return $this
   */
  public function setSessionId($session_id);

  /**
   * Gets the last processed checkpoint.
   *
   * @return string
   *   The last processed checkpoint.
   */
  public function getSourceLastSeq();

  /**
   * Sets the session id.
   *
   * @param string $source_last_seq
   *   The last processed checkpoint.
   *
   * @return $this
   */
  public function setSourceLastSeq($source_last_seq);

}
