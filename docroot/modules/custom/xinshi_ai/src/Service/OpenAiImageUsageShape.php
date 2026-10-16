<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Repairs usage fields of image responses before the strict SDK parses them.
 *
 * The openai-php client maps /images/* responses onto required int fields
 * without defaults: usage.total_tokens, usage.input_tokens_details.text_tokens
 * and usage.input_tokens_details.image_tokens. A gateway that relays or
 * recomputes usage can omit one of them, which turns a successful HTTP 200
 * into a PHP TypeError while the response is parsed, and the image job into
 * an unretryable `internal` failure. The middleware fills the missing fields
 * with neutral defaults before the body reaches the SDK; complete responses,
 * error bodies and non-JSON bodies pass through untouched.
 */
final class OpenAiImageUsageShape {

  public const MIDDLEWARE = 'xinshi_ai_openai_usage_shape';

  public static function middleware(): callable {
    return static function (callable $handler): callable {
      return static function (RequestInterface $request, array $options) use ($handler) {
        if (!str_contains($request->getUri()->getPath(), '/images/')) {
          return $handler($request, $options);
        }
        return $handler($request, $options)->then(
          static fn (ResponseInterface $response): ResponseInterface => self::repair($response),
        );
      };
    };
  }

  /**
   * Fills the usage keys the SDK requires; everything else passes through.
   */
  private static function repair(ResponseInterface $response): ResponseInterface {
    if ($response->getStatusCode() >= 300) {
      return $response;
    }
    $stream = $response->getBody();
    // Reading a non-seekable stream would consume it for the SDK as well.
    if (!$stream->isSeekable()) {
      return $response;
    }
    $body = (string) $stream;
    // Cheap guard: image payloads can be large and most carry no usage.
    if (!str_contains($body, '"usage"')) {
      return $response;
    }
    $data = json_decode($body, TRUE);
    if (!is_array($data) || !is_array($data['usage'] ?? NULL)) {
      return $response;
    }
    $usage = $data['usage'];
    if (!isset($usage['total_tokens'])) {
      $usage['total_tokens'] = self::sumTokens($usage);
    }
    if (is_array($usage['input_tokens_details'] ?? NULL)) {
      foreach (['text_tokens', 'image_tokens'] as $field) {
        if (!isset($usage['input_tokens_details'][$field])) {
          $usage['input_tokens_details'][$field] = 0;
        }
      }
    }
    if ($usage === $data['usage']) {
      return $response;
    }
    $data['usage'] = $usage;
    try {
      $json = json_encode($data, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      return $response;
    }
    return $response->withBody(Utils::streamFor($json))
      ->withHeader('Content-Length', (string) strlen($json));
  }

  /**
   * The observed input + output tokens, or 0 when neither is a number.
   */
  private static function sumTokens(array $usage): int {
    $sum = 0;
    foreach (['input_tokens', 'output_tokens'] as $field) {
      if (is_numeric($usage[$field] ?? NULL)) {
        $sum += (int) $usage[$field];
      }
    }
    return $sum;
  }

}
