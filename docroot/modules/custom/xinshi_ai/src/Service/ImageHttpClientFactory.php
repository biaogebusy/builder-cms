<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\xinshi_ai\Exception\ImageSafetyException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Buffered, bounded HTTP for image providers and returned image URLs.
 */
class ImageHttpClientFactory {

  public const MAX_RESPONSE_BYTES = 100_663_296;

  public function __construct(private readonly PublicUrlPolicy $policy) {}

  public function create(array $options = [], int $limit = self::MAX_RESPONSE_BYTES): Client {
    if (!extension_loaded('curl')) {
      throw new ImageSafetyException('The image transport requires cURL.');
    }
    $stack = isset($options['handler']) && $options['handler'] instanceof HandlerStack
      ? clone $options['handler'] : HandlerStack::create();
    // A stream-handler fallback would ignore CURLOPT_RESOLVE and reopen SSRF.
    $stack->setHandler(new CurlHandler());
    // Check before the metering middleware marks a request as dispatched.
    $stack->unshift($this->middleware($limit), 'xinshi_ai.public_destination');
    return new Client(['handler' => $stack, 'allow_redirects' => FALSE, 'proxy' => '', 'verify' => TRUE,
      'timeout' => min(300, max(1, (float) ($options['timeout'] ?? 180))), 'connect_timeout' => 10]);
  }

  /**
   * Enforces policy at dispatch, including when DNS changes after submission.
   */
  public function middleware(int $limit): callable {
    return function (callable $next) use ($limit): callable {
      return function (RequestInterface $request, array $options) use ($next, $limit) {
        $target = $this->policy->destination((string) $request->getUri());
        $ip = str_contains($target['address'], ':') ? '[' . $target['address'] . ']' : $target['address'];
        $options['curl'] = [
          // Literal IP URLs are already pinned and need no DNS override.
          CURLOPT_RESOLVE => filter_var($target['host'], FILTER_VALIDATE_IP) ? [] : [$target['host'] . ':' . $target['port'] . ':' . $ip],
          // Do not reuse a connection established under a different DNS result.
          CURLOPT_FRESH_CONNECT => TRUE,
          CURLOPT_FORBID_REUSE => TRUE,
        ];
        $options['protocols'] = ['http', 'https'];
        $options['proxy'] = '';
        $options['verify'] = TRUE;
        $options['allow_redirects'] = FALSE;
        $options['stream'] = FALSE;
        $options['decode_content'] = FALSE;
        $options['sink'] = new BoundedImageResponse($limit);
        $options['on_headers'] = static function (ResponseInterface $response) use ($limit): void {
          if ($response->getStatusCode() >= 300 && $response->getStatusCode() < 400) {
            throw new ImageSafetyException('Remote redirects are not allowed.');
          }
          if ((int) $response->getHeaderLine('Content-Length') > $limit) {
            throw new ImageSafetyException('Remote response exceeds the byte limit.');
          }
        };
        return $next($request, $options);
      };
    };
  }

}
