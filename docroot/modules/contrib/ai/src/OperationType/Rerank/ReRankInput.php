<?php

declare(strict_types=1);

namespace Drupal\ai\OperationType\Rerank;

use Drupal\ai\OperationType\InputBase;

/**
 * Represents a request to rerank a list of documents.
 */
class ReRankInput extends InputBase {

  /**
   * The query to use for reranking.
   *
   * @var string
   */
  private string $query;

  /**
   * The inputs to rerank (text, images, etc., sent to API as 'documents').
   *
   * @var array
   */
  private array $inputs;

  /**
   * The number of documents to return. Set to 0 to return all documents.
   *
   * @var int
   */
  private int $topN;

  /**
   * Optional metadata for each input (kept for mapping results back).
   *
   * @var array
   */
  private array $metadata;

  /**
   * Constructs a new ReRankInput object.
   *
   * @param string $query
   *   The query to use for reranking.
   * @param array $inputs
   *   The inputs to rerank (text, images, etc., sent to API as 'documents').
   * @param int $topN
   *   The number of documents to return. Set to 0 to return all documents.
   * @param array $metadata
   *   Optional metadata for each input (kept for mapping results back).
   */
  public function __construct(
    string $query,
    array $inputs,
    int $topN = 0,
    array $metadata = [],
  ) {
    $this->query = $query;
    $this->inputs = $inputs;
    $this->topN = $topN;
    $this->metadata = $metadata;
  }

  /**
   * Get the query.
   *
   * @return string
   *   The query to use for reranking.
   */
  public function getQuery(): string {
    return $this->query;
  }

  /**
   * Set the query.
   *
   * @param string $query
   *   The query to use for reranking.
   */
  public function setQuery(string $query): void {
    $this->query = $query;
  }

  /**
   * Get the inputs.
   *
   * @return array
   *   The inputs to rerank (text, images, etc.).
   */
  public function getInputs(): array {
    return $this->inputs;
  }

  /**
   * Set the inputs.
   *
   * @param array $inputs
   *   The inputs to rerank (text, images, etc.).
   */
  public function setInputs(array $inputs): void {
    $this->inputs = $inputs;
  }

  /**
   * Get the top N value.
   *
   * @return int
   *   The number of documents to return. Returns 0 if all documents should be
   *   returned.
   */
  public function getTopN(): int {
    return $this->topN;
  }

  /**
   * Set the top N value.
   *
   * @param int $topN
   *   The number of documents to return. Set to 0 to return all documents.
   */
  public function setTopN(int $topN): void {
    $this->topN = $topN;
  }

  /**
   * Get the metadata.
   *
   * @return array
   *   Optional metadata for each input.
   */
  public function getMetadata(): array {
    return $this->metadata;
  }

  /**
   * Set the metadata.
   *
   * @param array $metadata
   *   Optional metadata for each input.
   */
  public function setMetadata(array $metadata): void {
    $this->metadata = $metadata;
  }

  /**
   * Converts the ReRankInput object to an array.
   *
   * Note: Returns inputs rather than documents to maintain consistency
   * with internal property naming. Provider implementations are responsible
   * for mapping inputs to documents when calling external APIs.
   *
   * @return array{query: string, top_n: int, inputs: array}
   *   An array representation of the ReRankInput object.
   */
  public function toArray(): array {
    return parent::toArray() + [
      'query' => $this->query,
      'top_n' => $this->topN,
      'inputs' => $this->inputs,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function toString(): string {
    return \sprintf('ReRankInput: %s', $this->query);
  }

}
