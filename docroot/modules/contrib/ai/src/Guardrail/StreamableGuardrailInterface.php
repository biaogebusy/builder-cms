<?php

declare(strict_types=1);

namespace Drupal\ai\Guardrail;

use Drupal\ai\Guardrail\Result\GuardrailResultInterface;

/**
 * Interface for guardrails that can evaluate content during streaming.
 *
 * A streaming guardrail hooks into the stream iteration and dynamically
 * decides when to start buffering by providing a start regex. Content streams
 * through normally until the start pattern is detected in the lookahead window.
 * Once a match is found, the triggering chunk is suppressed and the iterator
 * starts accumulating subsequent content in an internal buffer. Any content
 * that arrived before the match was already yielded to the consumer.
 *
 * When the stop regex matches the accumulated buffer (or the stream ends), the
 * buffer is passed to processStreamedBuffer() and the result determines whether
 * the content is released, suppressed, or rewritten before it reaches the
 * consumer.
 *
 * Content that never triggers the start regex streams through normally with no
 * delay.
 */
interface StreamableGuardrailInterface extends AiGuardrailInterface {

  /**
   * Returns the regex that triggers buffering when matched on a stream chunk.
   *
   * The pattern is tested via preg_match() against a lookahead window that
   * accumulates recent flushed chunks. When it matches, the triggering chunk
   * is suppressed and buffering begins from that point. Any content already
   * yielded before the match reaches the consumer.
   *
   * An empty string disables the start check; in that case buffering begins
   * immediately from the very first chunk.
   *
   * @return string
   *   A valid PCRE regex string including delimiters (e.g. '/SECRET/i'), or an
   *   empty string to start buffering immediately.
   */
  public function getStartRegex(): string;

  /**
   * Returns the regex that ends buffering and triggers content evaluation.
   *
   * The pattern is tested via preg_match() against the entire accumulated
   * buffer after each new chunk is appended. When it matches, the full buffer
   * is passed to processStreamedBuffer().
   *
   * If the stream ends while buffering is still active, the buffer is
   * evaluated regardless of whether the stop regex has matched.
   *
   * An empty string means buffering only ends when the stream ends.
   *
   * @return string
   *   A valid PCRE regex string including delimiters (e.g. '/END_MARKER/'),
   *   or an empty string to evaluate only at end-of-stream.
   */
  public function getStopRegex(): string;

  /**
   * Evaluates the content that was buffered between start and stop patterns.
   *
   * The returned result controls what the consumer ultimately receives:
   * - PassResult: the original buffered content is released unchanged.
   * - StopResult: the buffered content is suppressed; the result message is
   *   yielded to the consumer instead.
   * - RewriteOutputResult: the result message replaces the buffered content.
   *
   * This method is also called when the stream ends while buffering is still
   * active (stop regex never matched), and when the buffer grows beyond the
   * iterator's maxGuardrailBufferSize limit (force-evaluation to prevent
   * unbounded memory growth). Implementations should handle partial content
   * gracefully in both cases.
   *
   * @param string $buffered_content
   *   The full text accumulated since the start regex matched.
   *
   * @return \Drupal\ai\Guardrail\Result\GuardrailResultInterface
   *   The evaluation result.
   */
  public function processStreamedBuffer(string $buffered_content): GuardrailResultInterface;

}
