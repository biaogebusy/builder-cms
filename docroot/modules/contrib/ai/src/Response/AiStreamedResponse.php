<?php

namespace Drupal\ai\Response;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A StreamedResponse with correct default headers for AI streaming.
 *
 * This class provides a centralized way to create streaming HTTP responses
 * for AI operations. It sets the required headers to ensure streaming works
 * correctly through common web server stacks (nginx, Varnish, etc.).
 *
 * Default headers:
 * - X-Accel-Buffering: no (disables nginx/FastCGI buffering)
 * - Surrogate-Control: no-store (tells Varnish/CDNs not to buffer/cache)
 * - Cache-Control: no-cache (prevents proxy caching)
 * - Content-Type: text/plain; charset=UTF-8 (callers can override)
 *
 * @see \Drupal\big_pipe\Render\BigPipeResponse
 * @see https://www.drupal.org/docs/8/core/modules/big-pipe/bigpipe-environment-requirements
 */
class AiStreamedResponse extends StreamedResponse {

  /**
   * Constructs a new AiStreamedResponse.
   *
   * @param callable|null $callback
   *   The response callback.
   * @param int $status
   *   The HTTP status code.
   * @param array $headers
   *   An array of HTTP headers. These are merged with the defaults, with
   *   caller-provided headers taking precedence.
   */
  public function __construct(?callable $callback = NULL, int $status = 200, array $headers = []) {
    $defaultHeaders = [
      'X-Accel-Buffering' => 'no',
      'Surrogate-Control' => 'no-store',
      'Cache-Control' => 'no-cache',
      'Content-Type' => 'text/plain; charset=UTF-8',
    ];
    $headers = array_merge($defaultHeaders, $headers);
    parent::__construct($callback, $status, $headers);
  }

  /**
   * {@inheritdoc}
   */
  public function sendContent(): static {
    // Clear all PHP output buffers so streamed data is sent to the client
    // immediately. Without this, Drupal/PHP output buffers capture the
    // callback output and only release it when the buffer is full or the
    // request ends, defeating the purpose of streaming. Callers only need
    // to call flush() after each chunk.
    while (ob_get_level() > 0) {
      ob_end_flush();
    }
    return parent::sendContent();
  }

}
