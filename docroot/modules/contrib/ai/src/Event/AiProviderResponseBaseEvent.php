<?php

namespace Drupal\ai\Event;

use Drupal\ai\OperationType\OutputInterface;

/**
 * Base abstract class for AI provider response events.
 *
 * Provides common functionality for AI-related response events including
 * output handling and response-specific metadata management.
 */
abstract class AiProviderResponseBaseEvent extends AiProviderRequestBaseEvent {

  /**
   * The output for the request.
   *
   * @var mixed
   */
  protected mixed $output;

  /**
   * Constructs the AI provider response event.
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
   * @param mixed $output
   *   The output for the request.
   * @param array $tags
   *   The tags for the request.
   * @param array $debugData
   *   The debug data for the request.
   * @param array $metadata
   *   The metadata to store for the request.
   */
  public function __construct(
    string $requestThreadId,
    string $providerId,
    string $operationType,
    array $configuration,
    mixed $input,
    string $modelId,
    mixed $output,
    array $tags = [],
    array $debugData = [],
    array $metadata = [],
  ) {
    parent::__construct(
      $requestThreadId,
      $providerId,
      $operationType,
      $configuration,
      $input,
      $modelId,
      $tags,
      $debugData,
      $metadata
    );
    $this->output = $output;
  }

  /**
   * Gets the output.
   *
   * @return \Drupal\ai\OperationType\OutputInterface
   *   The output.
   */
  public function getOutput(): OutputInterface {
    return $this->output;
  }

  /**
   * Sets the output.
   *
   * @param \Drupal\ai\OperationType\OutputInterface $output
   *   The output.
   */
  public function setOutput(OutputInterface $output): void {
    $this->output = $output;
  }

}
