<?php

namespace Drupal\replication\AllDocs;

interface AllDocsInterface {

  /**
   * @param boolean $include_docs
   * @return \Drupal\replication\AllDocs\AllDocsInterface
   */
  public function includeDocs($include_docs);

  /**
   * @param int $limit
   * @return \Drupal\replication\AllDocs\AllDocsInterface
   */
  public function limit($limit);

  /**
   * @param int $skip
   * @return \Drupal\replication\AllDocs\AllDocsInterface
   */
  public function skip($skip);

  /**
   * @param boolean $descending
   * @return \Drupal\replication\AllDocs\AllDocsInterface
   */
  public function descending($descending);

  /**
   * @param string $key
   * @return \Drupal\replication\AllDocs\AllDocsInterface
   */
  public function startKey($key);

  /**
   * @param string $key
   * @return \Drupal\replication\AllDocs\AllDocsInterface
   */
  public function endKey($key);

  /**
   * @param boolean $inclusive_end
   * @return \Drupal\replication\AllDocs\AllDocsInterface
   */
  public function inclusiveEnd($inclusive_end);

  /**
   * @return array
   */
  public function execute();

}
