<?php


namespace Drupal\workspace;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\multiversion\Entity\WorkspaceInterface;
use Drupal\replication\ReplicationTask\ReplicationTaskInterface;

/**
 * Provides an interface for defining Workspace pointer entities.
 *
 * @ingroup workspace
 */
interface WorkspacePointerInterface extends ContentEntityInterface, EntityChangedInterface {
  // Add get/set methods for your configuration properties here.

  /**
   * Gets the Workspace pointer name.
   *
   * @return string
   *   Name of the Workspace pointer.
   */
  public function getName();

  /**
   * Sets the Workspace pointer name.
   *
   * @param string $name
   *   The Workspace pointer name.
   *
   * @return \Drupal\workspace\WorkspacePointerInterface
   *   The called Workspace pointer entity.
   */
  public function setName($name);

  /**
   * Gets the Workspace pointer creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Workspace pointer.
   */
  public function getCreatedTime();

  /**
   * Sets the Workspace pointer creation timestamp.
   *
   * @param int $timestamp
   *   The Workspace pointer creation timestamp.
   *
   * @return \Drupal\workspace\WorkspacePointerInterface
   *   The called Workspace pointer entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Sets the Workspace this pointer references.
   *
   * @param \Drupal\multiversion\Entity\WorkspaceInterface $workspace
   *
   * @return $this
   *   The called Workspace pointer entity.
   */
  public function setWorkspace(WorkspaceInterface $workspace);

  /**
   * Returns the referenced workspace entity.
   *
   * @return \Drupal\multiversion\Entity\WorkspaceInterface
   *   The workspace entity.
   */
  public function getWorkspace();

  /**
   * Returns the referenced workspace ID.
   *
   * @return int|null
   *   The workspace ID, or NULL in case the workspace ID field has not been
   *   set on the entity.
   */
  public function getWorkspaceId();

  /**
   * Sets the referenced workspace ID.
   *
   * @param int $workspace_id
   *   The workspace id.
   *
   * @return $this
   */
  public function setWorkspaceId($workspace_id);

  /**
   * @param \Drupal\workspace\WorkspacePointerInterface $target
   * @param \Drupal\replication\ReplicationTask\ReplicationTaskInterface|null $task
   *
   * @return string
   *
   * @see \Relaxed\Replicator\Replication::generateReplicationId()
   */
  public function generateReplicationId(WorkspacePointerInterface $target, ReplicationTaskInterface $task = NULL);

  /**
   * Sets the availability of the workspace.
   *
   * @param bool $available
   *
   * @return $this
   */
  public function setWorkspaceAvailable($available = TRUE);

  /**
   * Returns the availability of the workspace.
   *
   * @return bool
   */
  public function getWorkspaceAvailable();

}
