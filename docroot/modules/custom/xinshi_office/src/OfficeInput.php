<?php

declare(strict_types=1);

namespace Drupal\xinshi_office;

use Drupal\Component\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;

/** Validates bounded protocol data without coercing or removing input fields. */
final class OfficeInput {

  public const SNAPSHOT_BYTES = 4194304;

  /** Reads one bounded JSON object, keeping objects distinct from arrays. */
  public static function read(Request $request): \stdClass {
    if ($request->getContentTypeFormat() !== 'json') {
      throw new OfficeException('invalid_input', 415);
    }
    $stream = $request->getContent(TRUE);
    $body = stream_get_contents($stream, self::SNAPSHOT_BYTES + 1);
    if (!is_string($body) || strlen($body) > self::SNAPSHOT_BYTES) {
      throw new OfficeException('content_limit', 413);
    }
    try {
      $data = json_decode($body, FALSE, 64, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      throw new OfficeException('invalid_input', 400);
    }
    if (!$data instanceof \stdClass) {
      throw new OfficeException('invalid_input');
    }
    return $data;
  }

  /** Requires exact named properties and preserves explicit nulls. */
  public static function object(mixed $value, array $required, array $optional = []): void {
    if (!$value instanceof \stdClass) {
      throw new OfficeException('invalid_input');
    }
    $keys = array_keys(get_object_vars($value));
    if (array_diff($required, $keys) || array_diff($keys, array_merge($required, $optional))) {
      throw new OfficeException('invalid_input');
    }
  }

  /** Accepts one canonical UUID representation. */
  public static function uuid(mixed $value): void {
    if (!is_string($value) || !Uuid::isValid($value) || strtolower($value) !== $value) {
      throw new OfficeException('invalid_id', 400);
    }
  }

  /** Counts UTF-16 code units like the editor and rejects XML-incompatible characters. */
  public static function text(mixed $value, int $maximum, bool $nonempty = FALSE): void {
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') ||
      strlen(mb_convert_encoding($value, 'UTF-16LE', 'UTF-8')) / 2 > $maximum ||
      preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x{FFFE}\x{FFFF}]/u', $value) ||
      ($nonempty && trim($value) === '')) {
      throw new OfficeException('invalid_text');
    }
  }

  /** Produces deterministic JSON for content and request digests; array order remains significant. */
  public static function encode(mixed $value): string {
    $sort = static function (mixed $item) use (&$sort): mixed {
      if ($item instanceof \stdClass) {
        $fields = get_object_vars($item);
        ksort($fields, SORT_STRING);
        return (object) array_map($sort, $fields);
      }
      return is_array($item) ? array_map($sort, $item) : $item;
    };
    $precision = ini_get('serialize_precision');
    ini_set('serialize_precision', '17');
    try {
      $json = json_encode($sort($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    catch (\JsonException) {
      throw new OfficeException('invalid_input');
    }
    finally {
      ini_set('serialize_precision', $precision);
    }
    if (strlen($json) > self::SNAPSHOT_BYTES) {
      throw new OfficeException('content_limit', 413);
    }
    return $json;
  }

}

