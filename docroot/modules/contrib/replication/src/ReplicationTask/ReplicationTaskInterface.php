<?php

namespace Drupal\replication\ReplicationTask;

/**
 * The replication settings between source and target.
 *
 * This interface defines the optional settings to be used during a replication
 * task between a source and a target workspace. These are based on CouchDB's
 * replication specifications.
 *
 * @see http://docs.couchdb.org/en/latest/json-structure.html#replication-settings
 */
interface ReplicationTaskInterface {

  /**
   * Set the ID of the filter plugin to use during replication.
   *
   * @param string $filter
   *   The plugin ID of a ReplicationFilterInterface.
   *
   * @return ReplicationTaskInterface
   *   Returns $this.
   */
  public function setFilter($filter = NULL);

  /**
   * Get the ID of the filter plugin to use during replication.
   *
   * @return string
   *   The plugin ID of a ReplicationFilterInterface.
   */
  public function getFilter();

  /**
   * Set the parameters for the filter plugin.
   *
   * @param array|NULL $parameters
   *   An associative array of name-value parameters.
   *
   * @return ReplicationTaskInterface
   *   Returns $this.
   */
  public function setParameters(array $parameters = NULL);

  /**
   * Set a parameter for the filter plugin.
   *
   * If no parameter bag yet exists, an empty parameter bag will be created.
   *
   * @param string $name
   *   The parameter name to set.
   * @param string $value
   *   The value for the parameter.
   *
   * @return ReplicationTaskInterface
   *   Returns $this.
   */
  public function setParameter($name, $value);

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
   * Set the limit of docs for BulkDocs per POST request.
   *
   * @param int $bulkDocsLimit
   *   The limit of the items to send per request.
   *
   * @return \Drupal\replication\Changes\ChangesInterface
   *   Returns $this.
   */
  public function setBulkDocsLimit($bulkDocsLimit);

  /**
   * Get the parameters for the filter plugin.
   *
   * @return array
   *   The parameters passed to the filter plugin.
   */
  public function getParameters();

  /**
   * Set the update sequence to start with.
   *
   * @param int $sinceSeq
   */
  public function setSinceSeq($sinceSeq);

  /**
   * @param mixed $style
   */
  public function setStyle($style);

  /**
   * Converts the parameter bag to an associative array and returns the array.
   *
   * @return string[string]
   *   An associative array of parameters passed to the filter fn.
   */
  public function getParametersAsArray();

  /**
   * Get a parameter's value.
   *
   * @param string $name
   *   The parameter name.
   *
   * @return mixed
   *   The parameter value.
   *
   * @throws \Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException
   *   If the parameter is not defined.
   */
  public function getParameter($name);

  /**
   * Returns the limit.
   *
   * @return int
   */
  public function getLimit();

  /**
   * Returns the BulkDocs limit.
   *
   * @return int
   */
  public function getBulkDocsLimit();

  /**
   * @return int
   */
  public function getSinceSeq();

  /**
   * @return mixed
   */
  public function getStyle();

  /**
   * @return array
   */
  public function getDocIds();

  /**
   * @param array $docIds
   */
  public function setDocIds($docIds);

  /**
   * @return int
   */
  public function getHeartbeat();

  /**
   * @param int $heartbeat
   */
  public function setHeartbeat($heartbeat);

  /**
   * @return boolean
   */
  public function getCreateTarget();

  /**
   * @param boolean $createTarget
   */
  public function setCreateTarget($createTarget);

  /**
   * @return bool
   */
  public function getContinuous();

  /**
   * @param bool $continuous
   */
  public function setContinuous($continuous);

}
