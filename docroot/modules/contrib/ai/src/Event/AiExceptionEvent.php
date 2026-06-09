<?php

namespace Drupal\ai\Event;

use Drupal\ai\OperationType\OutputInterface;

/**
 * Event dispatched when an AI provider call throws an exception.
 *
 * Subscribers can:
 *  - Rewrite the exception message via ::setMessage(). The original exception
 *    class is preserved so existing catch blocks keep working.
 *  - Provide a recovery response via ::setForcedOutputObject() to implement
 *    graceful failover (backup provider, cached response, canned output)
 *    without the caller having to handle the exception.
 *
 * Request context (provider id, operation type, model id, input, ...) is
 * inherited from AiProviderRequestBaseEvent so failover subscribers know
 * exactly what failed.
 */
final class AiExceptionEvent extends AiProviderRequestBaseEvent {

  /**
   * The current message, possibly rewritten by a subscriber.
   */
  private string $message;

  /**
   * The output to force return instead of rethrowing the exception.
   *
   * This is used if a third party wants to recover gracefully from a failed
   * provider call, for example by returning a cached response or the output
   * of a backup provider, rather than propagating the exception to the
   * original caller.
   */
  protected ?OutputInterface $forcedOutputObject = NULL;

  /**
   * Constructs a new AiExceptionEvent.
   *
   * @param \Exception $exception
   *   The exception thrown by the provider call.
   * @param string $requestThreadId
   *   The unique request thread id.
   * @param string $providerId
   *   The provider plugin id that threw.
   * @param string $operationType
   *   The operation type for the request (e.g. "chat").
   * @param array $configuration
   *   The provider configuration in effect at the time of the failure.
   * @param mixed $input
   *   The input the request was built from.
   * @param string $modelId
   *   The model id the request targeted.
   * @param array $tags
   *   Tags attached to the request.
   * @param array $debugData
   *   Debug data for the request.
   * @param array $metadata
   *   Additional metadata for the request.
   * @param string $message
   *   A custom message for the exception. Defaults to the exception's message.
   */
  public function __construct(
    public readonly \Exception $exception,
    string $requestThreadId,
    string $providerId,
    string $operationType,
    array $configuration,
    mixed $input,
    string $modelId,
    array $tags = [],
    array $debugData = [],
    array $metadata = [],
    string $message = '',
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
      $metadata,
    );
    $this->message = $message ?: $exception->getMessage();
  }

  /**
   * Sets a custom message for the exception.
   *
   * @param string $message
   *   The custom message to set.
   */
  public function setMessage(string $message): void {
    $this->message = $message;
  }

  /**
   * Gets the current message.
   *
   * @return string
   *   The current exception message.
   */
  public function getMessage(): string {
    return $this->message;
  }

  /**
   * Gets the exception to be thrown, possibly with a custom message.
   *
   * The original exception class is preserved so existing
   * catch (AiQuotaException $e) blocks keep working; only the message is
   * swapped if a subscriber rewrote it.
   *
   * @return \Exception
   *   The exception, with a rewritten message if one was set.
   */
  public function getException(): \Exception {
    if ($this->message === $this->exception->getMessage()) {
      return $this->exception;
    }
    $class = get_class($this->exception);
    return new $class($this->message, $this->exception->getCode(), $this->exception);
  }

  /**
   * Gets the forced output object.
   *
   * @return \Drupal\ai\OperationType\OutputInterface|null
   *   The output, or NULL if no subscriber has provided one.
   */
  public function getForcedOutputObject(): ?OutputInterface {
    return $this->forcedOutputObject;
  }

  /**
   * Sets the forced output object.
   *
   * When set, the proxy will return this output to the caller instead of
   * rethrowing the exception.
   *
   * @param \Drupal\ai\OperationType\OutputInterface $forced_output_object
   *   The output.
   */
  public function setForcedOutputObject(OutputInterface $forced_output_object): void {
    $this->forcedOutputObject = $forced_output_object;
  }

}
