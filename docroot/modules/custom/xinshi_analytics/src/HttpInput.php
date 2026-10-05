<?php

declare(strict_types=1);

namespace Drupal\xinshi_analytics;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Strict bounded JSON decoding shared by count and evidence HTTP boundaries. */
final class HttpInput {

  /** Keeps JSON object/array distinctions until query shape validation completes. */
  public static function read(Request $request): \stdClass {
    if (strtolower(trim(explode(';', $request->headers->get('Content-Type', ''))[0])) !== 'application/json') {
      throw new HttpException(415);
    }
    $body = stream_get_contents($request->getContent(TRUE), 1024 * 1024 + 1);
    if ($body === FALSE || strlen($body) > 1024 * 1024) {
      throw new HttpException(413);
    }
    try {
      $shape = json_decode($body, FALSE, 32, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      throw new AnalyticsException('invalid_query');
    }
    if (!$shape instanceof \stdClass) {
      throw new AnalyticsException('invalid_query');
    }
    return $shape;
  }

  /** Validates wire container types before converting to the service's array representation. */
  public static function query(mixed $shape): array {
    if (!$shape instanceof \stdClass || !is_array($shape->dimensions ?? NULL)
      || !is_array($shape->filters ?? NULL) || !($shape->range ?? NULL) instanceof \stdClass) {
      throw new AnalyticsException('invalid_query');
    }
    return self::array($shape);
  }

  /** Converts a shape only after callers have checked required container distinctions. */
  public static function array(\stdClass $shape): array {
    return json_decode(json_encode($shape, JSON_THROW_ON_ERROR), TRUE, 32, JSON_THROW_ON_ERROR);
  }

}
