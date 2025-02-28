<?php

namespace Drupal\replication\ReplicationTask;

use InvalidArgumentException;

/**
 * {@inheritdoc}
 */
class ReplicationTask implements ReplicationTaskInterface {

  /**
   * The ID of the filter plugin to use during replication.
   *
   * @var string
   */
  protected $filter;

  /**
   * The parameters passed to the filter function.
   *
   * @var array
   */
  protected $parameters;

  /**
   * Number of items to return.
   *
   * @var int
   *   The limit of items.
   */
  protected $limit = 100;

  /**
   * Number of items to send pe BulkDocs request.
   *
   * @var int
   *   The limit of items.
   */
  private $bulkDocsLimit = 100;

  /**
   * @var string
   */
  protected $style = 'all_docs';

  /**
   * @var int
   */
  protected $heartbeat = 10000;

  /**
   * @var array
   */
  protected $docIds = NULL;

  /**
   * Start the results from the given update sequence.
   *
   * @var int
   *   The update sequence to start with.
   */
  private $sinceSeq = 0;

  /**
   * @var bool
   */
  protected $createTarget = FALSE;

  /**
   * @var bool
   */
  protected $continuous = FALSE;

  /**
   * {@inheritdoc}
   */
  public function setFilter($filter = NULL) {
    $this->filter = $filter;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilter() {
    return $this->filter;
  }

  /**
   * {@inheritdoc}
   */
  public function setParameters(array $parameters = NULL) {
    if ($parameters == NULL) {
      $parameters = [];
    }
    $this->parameters = $parameters;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setBulkDocsLimit($bulkDocsLimit) {
    $this->bulkDocsLimit = $bulkDocsLimit;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setParameter($name, $value) {
    if (!is_array($this->parameters)) {
      $this->setParameters([]);
    }
    $this->parameters[$name] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setSinceSeq($sinceSeq) {
    $this->sinceSeq = $sinceSeq;
    return $this;
  }

  /**
   * @param mixed $style
   */
  public function setStyle($style) {
    $this->style = $style;
  }

  /**
   * {@inheritdoc}
   */
  public function getParameters() {
    return $this->parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function getParametersAsArray() {
    return $this->parameters->all();
  }

  /**
   * {@inheritdoc}
   */
  public function getParameter($name) {
    return $this->parameters->get($name);
  }

  /**
   * {@inheritdoc}
   */
  public function getLimit() {
    return $this->limit;
  }

  /**
   * {@inheritdoc}
   */
  public function getBulkDocsLimit() {
    return $this->bulkDocsLimit;
  }

  /**
   * {@inheritdoc}
   */
  public function getSinceSeq() {
    return $this->sinceSeq;
  }

  /**
   * {@inheritdoc}
   */
  public function getStyle() {
    return $this->style;
  }

  /**
   * {@inheritdoc}
   */
  public function getDocIds() {
    return $this->docIds;
  }

  /**
   * {@inheritdoc}
   */
  public function setDocIds($docIds) {
    if ($docIds != NULL) {
      sort($docIds);
      if ($this->filter === NULL) {
        $this->filter = '_doc_ids';
      }
      elseif ($this->filter !== '_doc_ids') {
        throw new InvalidArgumentException('If docIds is specified, the filter should be set as _doc_ids');
      }
    }
    $this->docIds = $docIds;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHeartbeat() {
    return $this->heartbeat;
  }

  /**
   * {@inheritdoc}
   */
  public function setHeartbeat($heartbeat) {
    $this->heartbeat = $heartbeat;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreateTarget() {
    return $this->createTarget;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreateTarget($createTarget) {
    $this->createTarget = $createTarget;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getContinuous() {
    return $this->continuous;
  }

  /**
   * {@inheritdoc}
   */
  public function setContinuous($continuous) {
    $this->continuous = $continuous;
    return $this;
  }

}
