<?php

namespace Drupal\elasticsearch_connector\Event;

/**
 * Event triggered when index params are built.
 */
class IndexParamsEvent extends BaseParamsEvent {

  /**
   * OriginalIndexId.
   *
   * @var string
   */
  protected $originalIndexId;

  /**
   * BuildSearchParamsEvent constructor.
   *
   * @param string $indexName
   *   The index name.
   * @param array $params
   *   The params.
   * @param string $originalIndexId
   *   The originalIndexId.
   */
  public function __construct(string $indexName, array $params, string $originalIndexId) {
    parent::__construct($indexName, $params);
    $this->originalIndexId = $originalIndexId;
  }

  /**
   * Get the original Index Name.
   */
  public function getOriginalIndexId(): string {
    return $this->originalIndexId;
  }

}
