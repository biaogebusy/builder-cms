<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Contract;

/**
 * Validates usage events against the wire contract shared with the Node producer.
 *
 * This mirrors `parseUsageEvent()` in the application's
 * `src/chat-server/metering/usage-event.contract.ts`: same field names, same
 * enumerations, counts as decimal strings. Unknown extra keys are tolerated so
 * the schema can grow; changed semantics require a new schema or normalizer
 * version on both sides.
 */
final class UsageEventValidator {

  public const SCHEMA_VERSION = 1;
  public const EVENT_TYPES = ['attempt.prepared', 'attempt.observed'];
  public const PAYERS = ['platform', 'customer_key'];
  public const OUTCOMES = ['succeeded', 'failed', 'aborted', 'unknown', 'not_sent'];
  public const DISPATCH_STATES = ['not_sent', 'sent', 'unknown'];
  public const QUALITIES = ['reported', 'missing', 'invalid'];
  /** primary = may become the billing basis; auxiliary = platform help; repair = never charged. */
  public const BILLING_ROLES = ['primary', 'auxiliary', 'repair'];

  private const ID_MAX_LENGTH = 128;
  private const EVENT_ID_MAX_LENGTH = 191;
  private const REQUEST_ID_MAX_LENGTH = 191;
  private const LABEL_MAX_LENGTH = 64;
  private const TIMESTAMP_PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,9})?(Z|[+-]\d{2}:\d{2})$/';

  /**
   * Returns the validated event with only the contract fields, or throws.
   *
   * @throws \Drupal\xinshi_ai_usage\Contract\UsageContractException
   */
  public static function validate(mixed $value): array {
    if (!self::isRecord($value)) {
      throw new UsageContractException('invalid_event', 'event must be an object');
    }
    if (($value['schema_version'] ?? NULL) !== self::SCHEMA_VERSION) {
      throw new UsageContractException('unsupported_schema', 'unsupported schema_version');
    }
    $context = self::record($value, 'context');
    $provider = self::record($value, 'provider');
    $timing = self::record($value, 'timing');
    $revision = $value['observation_revision'] ?? NULL;
    if (!self::isCount($revision)) {
      throw new UsageContractException('invalid_field', 'observation_revision must be a non-negative integer');
    }
    $attemptNo = $context['attempt_no'] ?? NULL;
    if (!self::isCount($attemptNo) || $attemptNo < 1) {
      throw new UsageContractException('invalid_field', 'context.attempt_no must be a positive integer');
    }
    $outcome = self::optionalText($value, 'outcome');
    $billingRole = self::optionalText($context, 'billing_role');
    return [
      'schema_version' => self::SCHEMA_VERSION,
      'event_id' => self::id($value, 'event_id', self::EVENT_ID_MAX_LENGTH),
      'producer_id' => self::id($value, 'producer_id'),
      'site_id' => self::id($value, 'site_id'),
      'event_type' => self::oneOf(self::text($value, 'event_type'), self::EVENT_TYPES, 'event_type'),
      'operation_id' => self::id($value, 'operation_id'),
      'authorization_id' => self::optionalId($value, 'authorization_id'),
      'attempt_id' => self::id($value, 'attempt_id'),
      'logical_call_id' => self::id($value, 'logical_call_id'),
      'observation_revision' => $revision,
      'occurred_at' => self::timestamp($value, 'occurred_at'),
      'context' => [
        'billing_account_id' => self::optionalId($context, 'billing_account_id'),
        'actor_user_id' => self::optionalId($context, 'actor_user_id'),
        'feature' => self::id($context, 'feature', self::LABEL_MAX_LENGTH),
        'stage' => self::id($context, 'stage', self::LABEL_MAX_LENGTH),
        'attempt_no' => $attemptNo,
        'payer' => self::oneOf(self::text($context, 'payer'), self::PAYERS, 'context.payer'),
        // Absent on events recorded before UB2.1; the projection keeps NULL for those.
        'billing_role' => $billingRole === NULL ? NULL
          : self::oneOf($billingRole, self::BILLING_ROLES, 'context.billing_role'),
        'chat_run_id' => self::optionalText($context, 'chat_run_id'),
        'task_id' => self::optionalText($context, 'task_id'),
        'chat_id' => self::optionalText($context, 'chat_id'),
      ],
      'provider' => [
        'account_ref' => self::id($provider, 'account_ref'),
        'requested_model' => self::id($provider, 'requested_model'),
        'resolved_model' => self::optionalId($provider, 'resolved_model'),
        'gateway_request_id' => self::optionalId($provider, 'gateway_request_id', self::REQUEST_ID_MAX_LENGTH),
        'provider_request_id' => self::optionalId($provider, 'provider_request_id', self::REQUEST_ID_MAX_LENGTH),
      ],
      'dispatch_state' => self::oneOf(self::text($value, 'dispatch_state'), self::DISPATCH_STATES, 'dispatch_state'),
      'outcome' => $outcome === NULL ? NULL : self::oneOf($outcome, self::OUTCOMES, 'outcome'),
      'error_code' => self::optionalId($value, 'error_code', self::LABEL_MAX_LENGTH),
      'usage' => self::usage($value['usage'] ?? NULL),
      'timing' => [
        'started_at' => self::timestamp($timing, 'started_at'),
        'first_token_at' => self::timestamp($timing, 'first_token_at', TRUE),
        'finished_at' => self::timestamp($timing, 'finished_at', TRUE),
      ],
    ];
  }

  /**
   * Milliseconds since the epoch for a validated ISO 8601 timestamp.
   */
  public static function toMilliseconds(string $timestamp): int {
    $parsed = new \DateTimeImmutable($timestamp);
    return (int) $parsed->format('Uv');
  }

  /**
   * Deterministic JSON: objects sorted by key, so equal payloads hash equally.
   *
   * Decoded JSON objects become associative arrays; a list keeps its order.
   */
  public static function canonicalJson(mixed $value): string {
    if (is_array($value)) {
      if (array_is_list($value)) {
        return '[' . implode(',', array_map([self::class, 'canonicalJson'], $value)) . ']';
      }
      ksort($value, SORT_STRING);
      $pairs = [];
      foreach ($value as $key => $item) {
        $pairs[] = json_encode((string) $key, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ':' . self::canonicalJson($item);
      }
      return '{' . implode(',', $pairs) . '}';
    }
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
  }

  public static function payloadHash(array $event): string {
    return hash('sha256', self::canonicalJson($event));
  }

  private static function usage(mixed $value): ?array {
    if ($value === NULL) {
      return NULL;
    }
    if (!self::isRecord($value)) {
      throw new UsageContractException('invalid_usage', 'usage must be an object or null');
    }
    $quality = $value['quality'] ?? NULL;
    if (!in_array($quality, self::QUALITIES, TRUE)) {
      throw new UsageContractException('invalid_usage', 'usage.quality is invalid');
    }
    $version = $value['normalizer_version'] ?? NULL;
    if (!is_string($version) || $version === '') {
      throw new UsageContractException('invalid_usage', 'usage.normalizer_version is required');
    }
    $raw = $value['raw_usage'] ?? NULL;
    if ($raw !== NULL) {
      if (!self::isRecord($raw)) {
        throw new UsageContractException('invalid_usage', 'usage.raw_usage must map keys to non-negative integers');
      }
      foreach ($raw as $count) {
        if (!self::isCount($count)) {
          throw new UsageContractException('invalid_usage', 'usage.raw_usage must map keys to non-negative integers');
        }
      }
    }
    $hash = $value['raw_usage_hash'] ?? NULL;
    if ($hash !== NULL && !(is_string($hash) && preg_match('/^[0-9a-f]{64}$/', $hash))) {
      throw new UsageContractException('invalid_usage', 'usage.raw_usage_hash must be a sha256 hex digest or null');
    }
    $usage = [
      'normalizer_version' => $version,
      'quality' => $quality,
      'input_tokens_total' => self::count($value, 'input_tokens_total'),
      'input_tokens_cache_read' => self::count($value, 'input_tokens_cache_read'),
      'input_tokens_cache_write' => self::count($value, 'input_tokens_cache_write'),
      'output_tokens_total' => self::count($value, 'output_tokens_total'),
      'output_tokens_reasoning' => self::count($value, 'output_tokens_reasoning'),
      'provider_total_tokens' => self::count($value, 'provider_total_tokens'),
      'images_generated' => self::count($value, 'images_generated'),
      'raw_usage' => $raw,
      'raw_usage_hash' => $hash,
    ];
    $reason = $value['invalid_reason'] ?? NULL;
    if (is_string($reason) && $reason !== '') {
      $usage['invalid_reason'] = $reason;
    }
    return $usage;
  }

  private static function count(array $source, string $key): ?string {
    $field = $source[$key] ?? NULL;
    if ($field === NULL) {
      return NULL;
    }
    if (is_string($field) && preg_match('/^\d+$/', $field)) {
      return $field;
    }
    throw new UsageContractException('invalid_usage', "usage.$key must be a decimal string or null");
  }

  private static function record(array $source, string $key): array {
    $field = $source[$key] ?? NULL;
    if (!self::isRecord($field)) {
      throw new UsageContractException('invalid_field', "$key missing");
    }
    return $field;
  }

  private static function text(array $source, string $key): string {
    $field = $source[$key] ?? NULL;
    if (is_string($field) && $field !== '') {
      return $field;
    }
    throw new UsageContractException('invalid_field', "$key must be a non-empty string");
  }

  /**
   * Identifiers are stored in indexed ASCII columns, so they are bounded here.
   *
   * Every label the projection copies into a varchar_ascii column goes through
   * this check, so an accepted event can always be applied later.
   */
  private static function id(array $source, string $key, int $maxLength = self::ID_MAX_LENGTH): string {
    $field = self::text($source, $key);
    if (strlen($field) > $maxLength || preg_match('/[^\x21-\x7e]/', $field)) {
      throw new UsageContractException('invalid_field', "$key must be printable ASCII up to $maxLength bytes");
    }
    return $field;
  }

  private static function optionalId(array $source, string $key, int $maxLength = self::ID_MAX_LENGTH): ?string {
    if (($source[$key] ?? NULL) === NULL) {
      return NULL;
    }
    return self::id($source, $key, $maxLength);
  }

  private static function optionalText(array $source, string $key): ?string {
    $field = $source[$key] ?? NULL;
    if ($field === NULL) {
      return NULL;
    }
    if (is_string($field)) {
      return $field;
    }
    throw new UsageContractException('invalid_field', "$key must be a string or null");
  }

  private static function timestamp(array $source, string $key, bool $optional = FALSE): ?string {
    $field = $optional ? self::optionalText($source, $key) : self::text($source, $key);
    if ($field !== NULL && !preg_match(self::TIMESTAMP_PATTERN, $field)) {
      throw new UsageContractException('invalid_field', "$key must be ISO 8601");
    }
    return $field;
  }

  private static function oneOf(string $field, array $allowed, string $key): string {
    if (!in_array($field, $allowed, TRUE)) {
      throw new UsageContractException('invalid_field', "$key must be one of " . implode(', ', $allowed));
    }
    return $field;
  }

  private static function isRecord(mixed $value): bool {
    return is_array($value) && ($value === [] || !array_is_list($value));
  }

  private static function isCount(mixed $value): bool {
    return is_int($value) && $value >= 0;
  }

}
