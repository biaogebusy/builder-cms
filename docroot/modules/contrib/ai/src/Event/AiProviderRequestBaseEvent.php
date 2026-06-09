<?php

namespace Drupal\ai\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Base abstract class for AI provider request events.
 *
 * Provides common functionality for AI-related events including request
 * thread tracking and metadata management.
 */
abstract class AiProviderRequestBaseEvent extends Event {

  /**
   * The request parent id.
   *
   * @var string|null
   */
  protected ?string $requestParentId = NULL;

  /**
   * Constructs the base AI provider request event.
   *
   * @param string $requestThreadId
   *   The unique request thread id.
   * @param string $providerId
   *   The provider to process.
   * @param string $operationType
   *   The operation type for the request.
   * @param array $configuration
   *   The configuration of the provider.
   * @param mixed $input
   *   The input for the request.
   * @param string $modelId
   *   The model ID for the request.
   * @param array $tags
   *   The tags for the request.
   * @param array $debugData
   *   The debug data for the request.
   * @param array $metadata
   *   The metadata to store for the request.
   */
  public function __construct(
    protected string $requestThreadId,
    protected string $providerId,
    protected string $operationType,
    protected array $configuration,
    protected mixed $input,
    protected string $modelId,
    protected array $tags = [],
    protected array $debugData = [],
    protected array $metadata = [],
  ) {
  }

  /**
   * Gets the request thread id.
   *
   * @return string
   *   The request thread id.
   */
  public function getRequestThreadId(): string {
    return $this->requestThreadId;
  }

  /**
   * Gets the request parent id.
   *
   * @return string|null
   *   The request parent id or NULL if not set.
   */
  public function getRequestParentId(): ?string {
    return $this->requestParentId;
  }

  /**
   * Set the parent request id.
   *
   * @param string $request_parent_id
   *   The parent request id.
   */
  public function setRequestParentId(string $request_parent_id): void {
    $this->requestParentId = $request_parent_id;
  }

  /**
   * Get all the metadata.
   *
   * @return array
   *   All the metadata.
   */
  public function getAllMetadata(): array {
    return $this->metadata;
  }

  /**
   * Set all metadata replacing existing contents.
   *
   * @param array $metadata
   *   All the metadata.
   */
  public function setAllMetadata(array $metadata): void {
    $this->metadata = $metadata;
  }

  /**
   * Get specific metadata by key.
   *
   * @param string $metadata_key
   *   The key of the metadata to return.
   *
   * @return mixed
   *   The metadata for the provided key.
   */
  public function getMetadata(string $metadata_key): mixed {
    return $this->metadata[$metadata_key] ?? NULL;
  }

  /**
   * Add to the metadata by key.
   *
   * @param string $key
   *   The key.
   * @param mixed $value
   *   The value.
   */
  public function setMetadata(string $key, mixed $value): void {
    $this->metadata[$key] = $value;
  }

  /**
   * Gets the provider.
   *
   * @return string
   *   The provider id.
   */
  public function getProviderId(): string {
    return $this->providerId;
  }

  /**
   * Gets the configuration.
   *
   * @return array
   *   The configuration.
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * Sets the configuration.
   *
   * @param array $configuration
   *   The configuration.
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = $configuration;
  }

  /**
   * Gets the operation type.
   *
   * @return string
   *   The operation type.
   */
  public function getOperationType(): string {
    return $this->operationType;
  }

  /**
   * Gets the model ID.
   *
   * @return string
   *   The model ID.
   */
  public function getModelId(): string {
    return $this->modelId;
  }

  /**
   * Gets the input.
   *
   * @return mixed
   *   The input.
   */
  public function getInput(): mixed {
    return $this->input;
  }

  /**
   * Sets the input.
   *
   * @param mixed $input
   *   The input.
   */
  public function setInput(mixed $input): void {
    $this->input = $input;
  }

  /**
   * Gets the tags.
   *
   * @return array
   *   The tags.
   */
  public function getTags(): array {
    return $this->tags;
  }

  /**
   * Helper to set tags on the event.
   *
   * @param array $tags
   *   An array of tags to set. Will completely replace those in $this->tags.
   */
  public function setTags(array $tags): void {
    $this->tags = $tags;
  }

  /**
   * Allow to set a new tag.
   *
   * @param string $tag
   *   The tag.
   * @param mixed $value
   *   The value.
   */
  public function setTag(string $tag, mixed $value): void {
    $this->tags[$tag] = $value;
  }

  /**
   * Gets the debug data.
   *
   * @return array
   *   The debug data.
   */
  public function getDebugData(): array {
    return $this->debugData;
  }

  /**
   * Set extra debug data.
   *
   * @param string $key
   *   The key.
   * @param mixed $value
   *   The value.
   */
  public function setDebugData(string $key, mixed $value): void {
    $this->debugData[$key] = $value;
  }

}
