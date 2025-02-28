<?php

namespace Drupal\replication\Changes;

/**
 * Define and build a changeset for a Workspace.
 *
 * @todo {@link https://www.drupal.org/node/2282295 Implement remaining feed
 *   query types.}
 * @todo break this class into a value object and a service object: one that
 * defines the parameters for getting the changeset and the other for executing
 * the code to build the changeset
 */
interface ChangesInterface {

  /**
   * Set the ID of the filter plugin to use to refine the changeset.
   *
   * @param string $filter
   *   The plugin id of a Drupal\replication\Plugin\ReplicationFilterInterface.
   *
   * @return \Drupal\replication\Changes\ChangesInterface
   *   Returns $this.
   */
  public function filter($filter);

  /**
   * Set the parameters for the filter plugin.
   *
   * @param array $parameters
   *   The parameters passed to the filter plugin.
   *
   * @return \Drupal\replication\Changes\ChangesInterface
   *   Returns $this.
   */
  public function parameters(array $parameters = NULL);

  /**
   * Set the flag for including entities in the changeset.
   *
   * @param bool $include_docs
   *   Whether to include entities in the changeset.
   *
   * @return \Drupal\replication\Changes\ChangesInterface
   *   Returns $this.
   */
  public function includeDocs($include_docs);

  /**
   * Sets from what sequence number to check for changes.
   *
   * @param int $seq
   *   The sequence ID to start including changes from. Result includes last_seq.
   *
   * @return \Drupal\replication\Changes\ChangesInterface
   *   Returns $this.
   */
  public function setSince($seq);

  /**
   * Get the since value.
   *
   * @return int
   */
  public function getSince();

  /**
   * Sets until what sequence number to check for changes.
   *
   * @param int $seq
   *   The sequence ID to stop at.
   *
   * @return \Drupal\replication\Changes\ChangesInterface
   *   Returns $this.
   */
  public function setStop($seq);

  /**
   * Set the limit of returned number of items.
   *
   * @param int $limit
   *   The limit of returned items.
   *
   * @return \Drupal\replication\Changes\ChangesInterface
   *   Returns $this.
   */
  public function setLimit($limit);

  /**
   * Return the changes in a 'normal' way.
   */
  public function getNormal();

  /**
   * Return the changes with a 'longpoll'.
   *
   * We can implement this method later.
   *
   * @see https://www.drupal.org/node/2282295
   */
  public function getLongpoll();

}
