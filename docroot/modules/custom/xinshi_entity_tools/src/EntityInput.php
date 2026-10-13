<?php

declare(strict_types=1);

namespace Drupal\xinshi_entity_tools;

use Symfony\Component\HttpFoundation\Request;

/** Validates the bounded wire format without coercing approved field values. */
final class EntityInput {

  /** Reads an object while preserving the distinction between objects and lists. */
  public static function read(Request $request): \stdClass {
    if ($request->getContentTypeFormat() !== 'json' || strlen($request->getContent()) > 262144) {
      throw new EntityToolException('invalid_input', 400);
    }
    try {
      $input = json_decode($request->getContent(), FALSE, 16, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      throw new EntityToolException('invalid_input', 400);
    }
    if (!$input instanceof \stdClass) {
      throw new EntityToolException('invalid_input', 400);
    }
    return $input;
  }

  /** Rejects missing, additional and invalid common resource properties. */
  public static function resource(\stdClass $input, array $required, array $optional = []): void {
    $keys = array_keys(get_object_vars($input));
    $required = array_merge(['resourceId', 'resourceVersion', 'language'], $required);
    if (array_diff($required, $keys) || array_diff($keys, array_merge($required, $optional)) ||
      !is_string($input->resourceId) || !preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $input->resourceId) ||
      !is_int($input->resourceVersion) || $input->resourceVersion < 1 || $input->resourceVersion > 2147483647 ||
      !is_string($input->language) || !preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})*$/D', $input->language)) {
      throw new EntityToolException('invalid_input', 400);
    }
  }

  /** Validates an ID without accepting numbers, floats or SQL fragments. */
  public static function id(mixed $id): void {
    if (!is_string($id) || !preg_match('/^[1-9][0-9]{0,15}$/D', $id)) {
      throw new EntityToolException('invalid_input', 400);
    }
  }

  /** Validates explicit projections; arbitrary properties never reach field storage. */
  public static function fields(mixed $fields): void {
    if (!is_array($fields) || !array_is_list($fields) || !$fields || count($fields) > 30 ||
      array_filter($fields, fn($v) => !is_string($v) || !preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $v)) ||
      count(array_unique($fields)) !== count($fields)) {
      throw new EntityToolException('invalid_input', 400);
    }
  }

  /** Validates creation or a version-bound draft patch, never a full entity replacement. */
  public static function mutation(\stdClass $input): void {
    $update = ($input->operation ?? NULL) === 'update';
    self::resource($input, array_merge(['operation', 'values'], $update ? ['entityId', 'expectedVersion'] : []));
    if (!in_array($input->operation, ['create', 'update'], TRUE) || !$input->values instanceof \stdClass ||
      !get_object_vars($input->values) || count(get_object_vars($input->values)) > 30 ||
      strlen(json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) > 262144) {
      throw new EntityToolException('invalid_input', 400);
    }
    self::fields(array_keys(get_object_vars($input->values)));
    if ($update) {
      self::id($input->entityId);
      if (!is_string($input->expectedVersion) || !preg_match('/^[a-f0-9]{64}$/D', $input->expectedVersion)) {
        throw new EntityToolException('invalid_input', 400);
      }
    }
  }

  /** Canonical JSON makes operation deduplication independent of object key order. */
  public static function encode(\stdClass $input): string {
    $sort = function (mixed $value) use (&$sort): mixed {
      if ($value instanceof \stdClass) {
        $properties = get_object_vars($value);
        ksort($properties);
        return (object) array_map($sort, $properties);
      }
      return is_array($value) ? array_map($sort, $value) : $value;
    };
    return json_encode($sort($input), JSON_THROW_ON_ERROR);
  }

}
