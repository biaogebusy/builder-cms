<?php

declare(strict_types=1);

namespace Xinshi\ChatMigration;

use Drupal\xinshi_ai_reference\ChatSessionFormat;

/** Independently derives the Plan split from frozen source bytes, without accepting client ranges. */
final class PlanContent {

  private const MEMBERS = ['plan', 'originalPlan', 'planReviews', 'planId', 'planRunId'];

  // PCRE UTF mode alone does not give \s the same character set as JavaScript.
  private const FENCE_SPACE = '[\x{0009}-\x{000D}\x{0020}\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}]';

  /** Return a validated v3 snapshot, byte-preserving context, and deterministic UTF-16 proof. */
  public static function convert(\stdClass $before, string $uuid, array $chats, ?string $preserve = NULL): array {
    ChatSessionFormat::validate($before, $uuid, $chats);
    if ($before->storage_version !== 2 || $before->edit_version >= 9007199254740991) {
      throw new \RuntimeException('invalid_plan_source_version');
    }
    try {
      $result = self::split($before, $uuid, $chats);
    }
    catch (\RuntimeException $error) {
      if (!in_array($preserve, ['invalid_plan', 'unmapped_error_flag'], TRUE) ||
        $error->getMessage() !== $preserve) {
        throw $error;
      }
      $after = clone $before;
      $after->storage_version = 3;
      $after->edit_version++;
      $after->plan_context = NULL;
      ChatSessionFormat::validate($after, $uuid, $chats);
      $digest = hash('sha256', $before->content?->value ?? '');
      return ['fields' => $after, 'context' => NULL, 'proof' => (object) [
        'status' => 'preserved-exception', 'code' => $preserve, 'sourceContentSha256' => $digest,
        'targetContentSha256' => $digest, 'removals' => [],
      ]];
    }
    if ($preserve !== NULL) {
      throw new \RuntimeException('plan_preservation_mismatch');
    }
    return $result;
  }

  private static function split(\stdClass $before, string $uuid, array $chats): array {
    $after = clone $before;
    $after->storage_version = 3;
    $after->edit_version++;
    $after->plan_context = NULL;
    $body = $before->content?->value ?? '';
    $proof = ['status' => 'not-plan', 'sourceContentSha256' => hash('sha256', $body),
      'targetContentSha256' => hash('sha256', $body), 'removals' => []];
    $context = NULL;
    $document = $before->storage_mode === 'inline' && $before->session_role === 'assistant' &&
      $before->content !== NULL ? self::document($body) : NULL;
    if ($document !== NULL && property_exists($document['value'], 'plan')) {
      $value = $document['value'];
      if (property_exists($value, 'planId') && (!$value->plan instanceof \stdClass ||
        $value->planId !== ($value->plan->planId ?? NULL))) {
        throw new \RuntimeException('plan_identity_mismatch');
      }
      if (property_exists($value, 'planRunId') && $value->planRunId !== $uuid) {
        throw new \RuntimeException('legacy_run_identity_mismatch');
      }
      if (isset($value->isError) && $value->isError === TRUE && $before->is_error !== TRUE) {
        throw new \RuntimeException('unmapped_error_flag');
      }
      if (!array_intersect(array_keys(get_object_vars($value)), ['widget', 'summary', 'breakpoint'])) {
        throw new \RuntimeException('missing_artifact_body');
      }
      $members = $document['members'];
      $ranges = $metadata = [];
      for ($at = 0, $count = count($members); $at < $count;) {
        if (!in_array($members[$at]['key'], self::MEMBERS, TRUE)) {
          $at++;
          continue;
        }
        $first = $at;
        while ($at < $count && in_array($members[$at]['key'], self::MEMBERS, TRUE)) {
          $member = $members[$at++];
          $name = $member['key'] === 'planRunId' ? 'legacyPlanRunId' : $member['key'];
          $metadata[] = Archive::json($name) . ':' . substr($body,
            $document['start'] + $member['valueStart'], $member['valueEnd'] - $member['valueStart']);
        }
        $ranges[] = ['start' => $document['start'] +
          ($at === $count ? $members[$first - 1]['end'] : $members[$first]['start']),
          'end' => $document['start'] + ($at === $count ? $members[$at - 1]['end'] : $members[$at]['start'])];
      }
      $content = $body;
      foreach (array_reverse($ranges) as $range) {
        $content = substr($content, 0, $range['start']) . substr($content, $range['end']);
      }
      $expected = clone $value;
      foreach (self::MEMBERS as $name) {
        unset($expected->$name);
      }
      $retained = self::document($content);
      if ($retained === NULL || !Plan::same($retained['value'], $expected)) {
        throw new \RuntimeException('plan_artifact_changed');
      }
      $context = '{"version":1,' . implode(',', $metadata) . '}';
      $after->plan_context = ChatSessionFormat::decode($context);
      // Reject unusable legacy context before trying to relocate approvals within that context.
      ChatSessionFormat::validate($after, $uuid, $chats);
      $after->content = (object) ['value' => $content, 'format' => $before->content->format];
      $removals = array_map(static fn($range) => (object) [
        'start' => self::utf16(substr($body, 0, $range['start'])),
        'end' => self::utf16(substr($body, 0, $range['end'])),
      ], $ranges);
      if ($before->approval_timeline !== NULL) {
        $after->approval_timeline = array_map(static function ($entry) use ($removals) {
          $copy = clone $entry;
          $removed = 0;
          foreach ($removals as $range) {
            if ($entry->contentOffset > $range->start && $entry->contentOffset < $range->end) {
              throw new \RuntimeException('approval_inside_removed_metadata');
            }
            if ($entry->contentOffset >= $range->end) {
              $removed += $range->end - $range->start;
            }
          }
          $copy->contentOffset -= $removed;
          return $copy;
        }, $before->approval_timeline);
      }
      $proof = ['status' => 'split', 'sourceContentSha256' => hash('sha256', $body),
        'targetContentSha256' => hash('sha256', $content), 'removals' => $removals];
    }
    ChatSessionFormat::validate($after, $uuid, $chats);
    return ['fields' => $after, 'proof' => (object) $proof, 'context' => $context];
  }

  /** Change only stored offsets, preserving proposal JSON bytes and the reasoning positions. */
  public static function approvalBytes(string $raw, array $target): string {
    $before = ChatSessionFormat::decode($raw);
    if (Plan::same($before, $target)) {
      return $raw;
    }
    $depth = 0;
    $previous = '';
    $replacements = [];
    foreach (self::tokens($raw) as [$token, $start, $end]) {
      if ($depth === 2 && $token === ':' && str_starts_with($previous, '"') &&
        json_decode($previous) === 'contentOffset') {
        $offset = $end;
        while (isset($raw[$offset]) && str_contains(" \t\r\n", $raw[$offset])) {
          $offset++;
        }
        $finish = $offset;
        while (isset($raw[$finish]) && !str_contains(" \t\r\n,}", $raw[$finish])) {
          $finish++;
        }
        $index = count($replacements);
        if (!isset($target[$index])) {
          throw new \RuntimeException('approval_snapshot_mismatch');
        }
        $replacements[] = [$offset, $finish, (string) $target[$index]->contentOffset];
      }
      $depth += in_array($token, ['{', '['], TRUE) ? 1 :
        (in_array($token, ['}', ']'], TRUE) ? -1 : 0);
      $previous = $token;
    }
    if (count($replacements) !== count($target)) {
      throw new \RuntimeException('approval_snapshot_mismatch');
    }
    foreach (array_reverse($replacements) as [$start, $end, $text]) {
      $raw = substr($raw, 0, $start) . $text . substr($raw, $end);
    }
    if (!Plan::same(ChatSessionFormat::decode($raw), $target)) {
      throw new \RuntimeException('approval_snapshot_changed');
    }
    return $raw;
  }

  private static function document(string $body): ?array {
    $start = 0;
    $end = strlen($body);
    if (preg_match('/^' . self::FENCE_SPACE . '*```(?:json)?(?=[ \t\r\n{])[ \t]*(?:\r?\n)?/u', $body, $opening)) {
      $start = strlen($opening[0]);
      if (!preg_match('/```' . self::FENCE_SPACE . '*$/uD', $body, $closing, PREG_OFFSET_CAPTURE) ||
        $closing[0][1] < $start) {
        // An incomplete code block is ordinary text, not a registered Plan document.
        return NULL;
      }
      $end = $closing[0][1];
    }
    $raw = substr($body, $start, $end - $start);
    $value = json_decode($raw);
    if (json_last_error() !== JSON_ERROR_NONE || !$value instanceof \stdClass) {
      return NULL;
    }
    $value = ChatSessionFormat::decode($raw);
    $depth = 0;
    $previous = '';
    $pending = NULL;
    $members = [];
    foreach (self::tokens($raw) as [$token, $offset, $finish]) {
      if ($depth === 1 && $token === ':' && $pending !== NULL) {
        $pending['valueStart'] = $finish;
        while (str_contains(" \t\r\n", $raw[$pending['valueStart']])) {
          $pending['valueStart']++;
        }
      }
      if ($depth === 1 && in_array($token, [',', '}'], TRUE) && $pending !== NULL) {
        $pending['end'] = $offset;
        $pending['valueEnd'] = $offset;
        while (str_contains(" \t\r\n", $raw[$pending['valueEnd'] - 1])) {
          $pending['valueEnd']--;
        }
        $members[] = $pending;
        $pending = NULL;
      }
      if ($depth === 1 && str_starts_with($token, '"') && in_array($previous, ['{', ','], TRUE)) {
        $pending = ['key' => json_decode($token), 'start' => $offset];
      }
      $depth += in_array($token, ['{', '['], TRUE) ? 1 :
        (in_array($token, ['}', ']'], TRUE) ? -1 : 0);
      $previous = $token;
    }
    if (count($members) !== count(get_object_vars($value))) {
      throw new \RuntimeException('invalid_plan_member_ranges');
    }
    return ['value' => $value, 'members' => $members, 'start' => $start];
  }

  /** Iterate structural tokens without a PCRE size limit on a generated HTML string. */
  private static function tokens(string $raw): \Generator {
    for ($offset = 0, $length = strlen($raw); $offset < $length;) {
      $start = $offset;
      $char = $raw[$offset++];
      if ($char === '"') {
        while ($offset < $length) {
          $next = $raw[$offset++];
          if ($next === '\\') {
            $offset++;
          }
          elseif ($next === '"') {
            break;
          }
        }
        yield [substr($raw, $start, $offset - $start), $start, $offset];
      }
      elseif (str_contains('{}[]:,', $char)) {
        yield [$char, $start, $offset];
      }
    }
  }

  private static function utf16(string $text): int {
    return intdiv(strlen(mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')), 2);
  }

}
