<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Guzzle middleware that records whether an image request left the process.
 *
 * The contrib provider only exposes the decoded response body, so the facts
 * the usage contract needs from the transport are captured here, before the
 * body is parsed and before any image is dropped: whether the request was
 * dispatched at all, the HTTP status of a response that came back, and the
 * gateway request id header. Only `/images/` endpoints count; a moderation
 * call before the image request is not the metered request.
 */
final class ProviderRequestTrace {

  public const MIDDLEWARE = 'xinshi_ai_usage_trace';
  private const REQUEST_ID_HEADER = 'x-request-id';

  private bool $dispatched = FALSE;
  private ?int $statusCode = NULL;
  private ?string $requestId = NULL;

  /**
   * The middleware to push onto a Guzzle handler stack.
   */
  public function middleware(): callable {
    return function (callable $handler): callable {
      return function (RequestInterface $request, array $options) use ($handler) {
        if (!str_contains($request->getUri()->getPath(), '/images/')) {
          return $handler($request, $options);
        }
        $this->dispatched = TRUE;
        return $handler($request, $options)->then(function (ResponseInterface $response): ResponseInterface {
          $this->statusCode = $response->getStatusCode();
          $header = trim($response->getHeaderLine(self::REQUEST_ID_HEADER));
          $this->requestId = $header === '' ? NULL : $header;
          return $response;
        });
      };
    };
  }

  /**
   * `not_sent` before any image request, `unknown` without a response, `sent` with one.
   */
  public function dispatchState(): string {
    if (!$this->dispatched) {
      return 'not_sent';
    }
    return $this->statusCode === NULL ? 'unknown' : 'sent';
  }

  public function statusCode(): ?int {
    return $this->statusCode;
  }

  public function requestId(): ?string {
    return $this->requestId;
  }

}
