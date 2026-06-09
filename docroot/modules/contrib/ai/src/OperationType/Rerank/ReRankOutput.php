<?php

declare(strict_types=1);

namespace Drupal\ai\OperationType\Rerank;

use Drupal\ai\OperationType\OutputInterface;

/**
 * Represents a response from the rerank endpoint.
 */
class ReRankOutput implements OutputInterface {

  /**
   * Constructs a new ReRankOutput object.
   *
   * @param array $results
   *   The reranked documents.
   * @param string $id
   *   The ID of the request.
   * @param array $meta
   *   Additional metadata about the response.
   */
  public function __construct(
    public readonly array $results,
    public readonly string $id,
    public readonly array $meta,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getNormalized() {
    return $this->results;
  }

  /**
   * {@inheritdoc}
   */
  public function getRawOutput() {
    return $this->results;
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata() {
    return $this->meta;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return [
      'results' => $this->results,
      'id' => $this->id,
      'meta' => $this->meta,
    ];
  }

}
