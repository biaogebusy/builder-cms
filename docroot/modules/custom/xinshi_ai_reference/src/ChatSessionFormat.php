<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_reference;

/** Strict versioned message codec, independent of entity access and tool execution. */
final class ChatSessionFormat {

  public const RECORD_BYTES = 12 * 1024 * 1024;
  public const CONTENT_BYTES = 4 * 1024 * 1024;
  public const REVIEW_BYTES = 64 * 1024;
  public const APPROVAL_BYTES = 4 * 1024 * 1024;
  public const MAX_ITEMS = 1024;
  public const DEPTH = 64;
  private const SAFE_INTEGER = 9007199254740991;
  private const KEYS = ['storage_version', 'edit_version', 'storage_mode', 'title',
    'session_role', 'content', 'content_format', 'reasoning_content', 'summary',
    'total_tokens', 'img', 'uiparsing', 'style_preset', 'run_id', 'run_created_at_ms',
    'is_error', 'output_completion', 'ui_review', 'approval_timeline',
    'knowledge_refs', 'result_reference'];

  /** Decode JSON without last-wins duplicate keys or unsafe numeric coercion. */
  public static function decode(string $json): mixed {
    self::within($json, self::RECORD_BYTES);
    try {
      $value = json_decode($json, FALSE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      self::fail('invalid_json');
    }
    $stack = [];
    $previous = '';
    for ($offset = 0, $length = strlen($json); $offset < $length;) {
      if (str_contains(" \t\r\n", $json[$offset])) {
        $offset++;
        continue;
      }
      $start = $offset;
      $token = $json[$offset++];
      if ($token === '"') {
        // Walk string boundaries directly; PCRE backtracking limits can skip a long body.
        while ($offset < $length) {
          $char = $json[$offset++];
          if ($char === '\\') {
            $offset++;
          }
          elseif ($char === '"') {
            break;
          }
        }
        $token = substr($json, $start, $offset - $start);
      }
      elseif (!str_contains('{}[]:,', $token)) {
        while ($offset < $length && !str_contains(" \t\r\n{}[]:,", $json[$offset])) {
          $offset++;
        }
        $token = substr($json, $start, $offset - $start);
      }
      if ($token === '{' || $token === '[') {
        if (count($stack) >= self::DEPTH) {
          self::fail('limit_exceeded');
        }
        $stack[] = $token === '{' ? [] : NULL;
      }
      elseif ($token === '}' || $token === ']') {
        array_pop($stack);
      }
      elseif ($token === ':' && str_starts_with($previous, '"')) {
        $name = json_decode($previous, FALSE, 512, JSON_THROW_ON_ERROR);
        $index = count($stack) - 1;
        if (isset($stack[$index][$name])) {
          self::fail('invalid_json');
        }
        $stack[$index][$name] = TRUE;
      }
      elseif (str_starts_with($token, '-') && (float) $token == 0.0) {
        self::fail('invalid_json');
      }
      $previous = $token;
    }
    self::jsonValue($value);
    return $value;
  }

  /** Validate a complete snapshot; the caller still owns authorization and CAS. */
  public static function validate(mixed $value, string $session_id, array $conversation_ids): \stdClass {
    self::jsonValue($value);
    self::within(self::encode($value), self::RECORD_BYTES);
    if (!$value instanceof \stdClass) {
      self::fail('invalid_shape');
    }
    $version = $value->storage_version ?? NULL;
    if (!self::version($version, 2) && !self::version($version, 3)) {
      self::fail('unsupported_version');
    }
    if (!self::keys($value, $version == 3 ? [...self::KEYS, 'plan_context'] : self::KEYS)) {
      self::fail('invalid_shape');
    }
    if (!self::uuid($session_id) || !self::every($conversation_ids, self::uuid(...)) ||
      !self::positive($value->edit_version) ||
      ($value->run_id !== NULL && $value->run_id !== $session_id) ||
      ($value->run_created_at_ms !== NULL && !self::positive($value->run_created_at_ms))) {
      self::fail('invalid_identity');
    }
    if (!self::text($value->title, 255) || !self::text($value->session_role, 255, TRUE) ||
      !self::nullableText($value->content_format, 255) ||
      !self::nullableText($value->summary, 2500) || !self::nullableText($value->img, 255) ||
      !self::nullableText($value->style_preset, 255) ||
      !self::nullableBoolean($value->uiparsing) || !self::nullableBoolean($value->is_error) ||
      ($value->total_tokens !== NULL && !self::nonnegative($value->total_tokens)) ||
      !in_array($value->output_completion, [NULL, 'complete', 'incomplete'], TRUE)) {
      self::fail('invalid_shape');
    }
    if ($value->content !== NULL && (!self::keys($value->content, ['value', 'format']) ||
      !is_string($value->content->value) || !self::nullableText($value->content->format, 255))) {
      self::fail('invalid_body');
    }
    if ($value->reasoning_content !== NULL && !is_string($value->reasoning_content)) {
      self::fail('invalid_body');
    }
    self::within($value->content?->value ?? '', self::CONTENT_BYTES);
    self::within($value->reasoning_content ?? '', self::CONTENT_BYTES);
    if (!is_array($value->knowledge_refs) || !self::every($value->knowledge_refs, self::uuid(...)) ||
      count(array_unique($value->knowledge_refs)) !== count($value->knowledge_refs) ||
      ($value->knowledge_refs !== [] && $value->session_role !== 'user')) {
      self::fail('invalid_shape');
    }
    if (count($value->knowledge_refs) > self::MAX_ITEMS) {
      self::fail('limit_exceeded');
    }
    if (($value->run_id !== NULL && $value->session_role !== 'assistant') ||
      ($value->run_created_at_ms !== NULL && $value->run_id === NULL)) {
      self::fail('invalid_identity');
    }
    self::review($value->ui_review);
    if ($value->ui_review !== NULL && $value->session_role !== 'assistant') {
      self::fail('invalid_review');
    }
    self::approvals($value);
    if ($version == 3 && $value->plan_context !== NULL) {
      if ($value->storage_mode !== 'inline' || $value->session_role !== 'assistant') {
        self::fail('invalid_plan');
      }
      self::within(self::encode($value->plan_context), self::CONTENT_BYTES);
      self::planContext($value->plan_context, $session_id);
    }
    if ($value->storage_mode === 'protected_reference') {
      $ref = $value->result_reference;
      if (!self::keys($ref, ['kind', 'version', 'runId', 'chatId']) ||
        $ref->kind !== 'xinshi-protected-run' || !self::version($ref->version, 1) ||
        $ref->runId !== $session_id || $ref->runId !== $value->run_id ||
        !self::uuid($ref->chatId) || !self::every($conversation_ids, fn($id) => $id === $ref->chatId)) {
        self::fail('invalid_identity');
      }
      if ($value->title !== 'Protected task result' || $value->session_role !== 'assistant' ||
        $value->content !== NULL || $value->content_format !== NULL ||
        !self::empty($value->reasoning_content) || !self::empty($value->summary) ||
        !self::empty($value->img) || !self::empty($value->style_preset) ||
        ($value->total_tokens !== NULL && $value->total_tokens != 0) ||
        ($value->uiparsing !== NULL && $value->uiparsing !== FALSE) ||
        $value->is_error !== NULL || $value->output_completion !== NULL ||
        $value->ui_review !== NULL || $value->approval_timeline !== NULL ||
        $value->knowledge_refs !== []) {
        self::fail('protected_copy');
      }
    }
    elseif ($value->storage_mode !== 'inline' || $value->result_reference !== NULL) {
      self::fail('invalid_shape');
    }
    return $value;
  }

  /** Encode data without storing derived HTML or changing Unicode text. */
  public static function encode(mixed $value): string {
    try {
      return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS);
    }
    catch (\JsonException) {
      self::fail('invalid_json');
    }
  }

  /** Validate display/context data without inferring workflow state or execution authority. */
  private static function planContext(mixed $value, string $session_id): void {
    if (!self::keys($value, ['version', 'plan'],
      ['originalPlan', 'planReviews', 'planId', 'legacyPlanRunId']) ||
      !self::version($value->version, 1)) {
      self::fail('invalid_plan');
    }
    self::blueprint($value->plan);
    if (property_exists($value, 'originalPlan')) {
      self::blueprint($value->originalPlan);
    }
    if ((property_exists($value, 'planId') && $value->planId !== $value->plan->planId) ||
      (property_exists($value, 'legacyPlanRunId') && $value->legacyPlanRunId !== $session_id)) {
      self::fail('invalid_plan');
    }
    if (!property_exists($value, 'planReviews')) {
      return;
    }
    if (!is_array($value->planReviews) || count($value->planReviews) > self::MAX_ITEMS) {
      self::fail('invalid_plan');
    }
    foreach ($value->planReviews as $review) {
      if (!self::keys($review, ['round', 'ok', 'score'],
        ['rubric', 'issues', 'revisedPlan', 'sectionPatches']) ||
        !self::positive($review->round) || !is_bool($review->ok) || !self::planScore($review->score)) {
        self::fail('invalid_plan');
      }
      if (property_exists($review, 'rubric') && (!$review->rubric instanceof \stdClass ||
        !self::every(get_object_vars($review->rubric),
          fn($score) => $score === NULL || self::planScore($score)))) {
        self::fail('invalid_plan');
      }
      if (property_exists($review, 'issues') && (!is_array($review->issues) ||
        !self::every($review->issues, is_string(...)))) {
        self::fail('invalid_plan');
      }
      if (property_exists($review, 'revisedPlan')) {
        self::blueprint($review->revisedPlan);
      }
      if (property_exists($review, 'sectionPatches') && (!is_array($review->sectionPatches) ||
        !self::every($review->sectionPatches, fn($patch) => $patch instanceof \stdClass &&
          is_string($patch->id ?? NULL) && !self::blank($patch->id)))) {
        self::fail('invalid_plan');
      }
    }
  }

  private static function blueprint(mixed $value): void {
    if (!self::keys($value, ['planId', 'goal', 'audience', 'tone', 'type', 'sections'],
      ['designTokens', 'rules', 'promptGuide', 'states', 'personality']) ||
      !is_string($value->planId) || self::blank($value->planId) ||
      !is_string($value->goal) || self::blank($value->goal) ||
      !is_string($value->audience) || !is_string($value->tone) ||
      !in_array($value->type, ['fullHTML', 'custom-template'], TRUE) ||
      !is_array($value->sections) || $value->sections === []) {
      self::fail('invalid_plan');
    }
    foreach (['designTokens', 'rules', 'promptGuide', 'states', 'personality'] as $key) {
      if (property_exists($value, $key) && !$value->$key instanceof \stdClass) {
        self::fail('invalid_plan');
      }
    }
    $ids = [];
    foreach ($value->sections as $section) {
      if (!$section instanceof \stdClass || !is_string($section->id ?? NULL) ||
        self::blank($section->id) || in_array($section->id, $ids, TRUE) ||
        !is_string($section->type ?? NULL) || self::blank($section->type) ||
        !is_string($section->purpose ?? NULL)) {
        self::fail('invalid_plan');
      }
      $ids[] = $section->id;
    }
  }

  private static function planScore(mixed $value): bool {
    return (is_int($value) || is_float($value)) && is_finite((float) $value) &&
      $value >= 0 && $value <= 100;
  }

  private static function review(mixed $value): void {
    if ($value === NULL) {
      return;
    }
    if (!self::keys($value, ['version', 'report', 'lastReviewRun']) || !self::version($value->version, 1) ||
      ($value->report === NULL && $value->lastReviewRun === NULL)) {
      self::fail('invalid_review');
    }
    $marker = $value->lastReviewRun;
    if ($marker !== NULL && (!self::keys($marker, ['id', 'createdAt']) ||
      !self::uuid($marker->id) || !self::positive($marker->createdAt))) {
      self::fail('invalid_review');
    }
    $report = $value->report;
    if ($report !== NULL) {
      if (!self::keys($report, ['version', 'type', 'sourceHash', 'status', 'summary',
        'issues', 'captures', 'limitations'], ['execution']) || !self::version($report->version, 1) ||
        !in_array($report->type, ['fullHTML', 'custom-template'], TRUE) ||
        !is_string($report->sourceHash) || !preg_match('/^[a-f0-9]{64}$/D', $report->sourceHash) ||
        !in_array($report->status, ['reviewed', 'unavailable'], TRUE) ||
        !self::reviewText($report->summary, 1600) || !is_array($report->issues) ||
        count($report->issues) > 12 || !is_array($report->captures) || count($report->captures) > 6 ||
        !is_array($report->limitations) || count($report->limitations) > 8 ||
        !self::every($report->limitations, fn($item) => self::reviewText($item, 500))) {
        self::fail('invalid_review');
      }
      if (property_exists($report, 'execution')) {
        self::execution($report->execution);
      }
      $ids = [];
      foreach ($report->captures as $capture) {
        if (!self::keys($capture, ['id', 'viewport', 'width', 'height', 'y', 'documentHeight']) ||
          !is_string($capture->id) || !preg_match('/^(desktop|mobile)-\d+$/D', $capture->id) ||
          !in_array($capture->viewport, ['desktop', 'mobile'], TRUE) ||
          !self::geometry($capture->width) || !self::geometry($capture->height) ||
          !self::geometry($capture->documentHeight) || !self::nonnegative($capture->y) ||
          $capture->y + $capture->height > $capture->documentHeight ||
          $capture->id !== $capture->viewport . '-' . $capture->y || isset($ids[$capture->id])) {
          self::fail('invalid_review');
        }
        $ids[$capture->id] = TRUE;
      }
      foreach ($report->issues as $issue) {
        if (!self::keys($issue, ['captureId', 'severity', 'area', 'problem', 'suggestion']) ||
          !is_string($issue->captureId) || !isset($ids[$issue->captureId]) ||
          !in_array($issue->severity, ['error', 'warning'], TRUE) ||
          !self::reviewText($issue->area, 200) || !self::reviewText($issue->problem, 800) ||
          !self::reviewText($issue->suggestion, 800)) {
          self::fail('invalid_review');
        }
      }
      if (($report->status === 'reviewed' && $report->captures === []) ||
        ($report->status === 'unavailable' && $report->issues !== [])) {
        self::fail('invalid_review');
      }
    }
    self::within(self::encode($value), self::REVIEW_BYTES);
  }

  /** Validate bounded browser observations without accepting raw logs or screenshot copies. */
  private static function execution(mixed $value): void {
    $categories = [
      'script' => ['ok', 'script-error'], 'resources' => ['ok', 'resource-failed'],
      'network' => ['ok', 'network-blocked'], 'overflow' => ['ok', 'overflow'],
      'accessibility' => ['ok', 'axe-violation', 'axe-incomplete', 'check-unavailable'],
      'interaction' => ['ok', 'step-failed', 'check-unavailable', 'invalid-plan', 'not-provided'],
    ];
    $statuses = [
      'passed' => ['ok'],
      'failed' => ['script-error', 'resource-failed', 'overflow', 'axe-violation', 'step-failed'],
      'blocked' => ['network-blocked', 'axe-incomplete', 'check-unavailable', 'invalid-plan'],
      'skipped' => ['not-provided'],
    ];
    if (!self::keys($value, ['version', 'status', 'checks']) || !self::version($value->version, 1) ||
      !in_array($value->status, ['completed', 'unavailable'], TRUE) ||
      !is_array($value->checks) || count($value->checks) > 32) {
      self::fail('invalid_review');
    }
    $ids = [];
    foreach ($value->checks as $check) {
      if (!self::keys($check, ['id', 'viewport', 'kind', 'status', 'code', 'count'], ['label', 'step']) ||
        !is_string($check->id) || !preg_match('/^(desktop|mobile)-[a-z]+-\d+$/D', $check->id) ||
        !in_array($check->viewport, ['desktop', 'mobile'], TRUE) ||
        !is_string($check->kind) || !isset($categories[$check->kind]) ||
        !is_string($check->status) || !isset($statuses[$check->status]) ||
        !str_starts_with($check->id, $check->viewport . '-' . $check->kind . '-') ||
        isset($ids[$check->id]) || !in_array($check->code, $categories[$check->kind], TRUE) ||
        !in_array($check->code, $statuses[$check->status], TRUE) ||
        !self::nonnegative($check->count) || $check->count > 100000 ||
        (property_exists($check, 'label') && !self::reviewText($check->label, 80)) ||
        (property_exists($check, 'step') && ($check->kind !== 'interaction' ||
          !self::positive($check->step) || $check->step > 12)) ||
        ($check->status === 'passed' && $check->kind !== 'interaction' && $check->count != 0) ||
        ($check->status === 'failed' && $check->count == 0)) {
        self::fail('invalid_review');
      }
      $ids[$check->id] = TRUE;
    }
  }

  private static function approvals(\stdClass $data): void {
    $values = $data->approval_timeline;
    if ($values === NULL) {
      return;
    }
    if (!is_array($values) || $data->run_id === NULL || $data->session_role !== 'assistant') {
      self::fail('invalid_approval');
    }
    if (count($values) > self::MAX_ITEMS) {
      self::fail('limit_exceeded');
    }
    self::within(self::encode($values), self::APPROVAL_BYTES);
    $ids = $executions = [];
    $content_offset = $reasoning_offset = 0;
    foreach ($values as $entry) {
      if (!self::keys($entry, ['contentOffset', 'reasoningOffset', 'approval']) ||
        !self::nonnegative($entry->contentOffset) || $entry->contentOffset < $content_offset ||
        $entry->contentOffset > self::utf16($data->content?->value ?? '') ||
        !self::nonnegative($entry->reasoningOffset) || $entry->reasoningOffset < $reasoning_offset ||
        $entry->reasoningOffset > self::utf16($data->reasoning_content ?? '')) {
        self::fail('invalid_approval');
      }
      $approval = $entry->approval;
      if (!self::keys($approval, ['runId', 'approvalId', 'toolCallId', 'executionId',
        'proposal', 'status', 'executionStatus', 'expiresAt'], ['decidedAt', 'decidedBy']) ||
        $approval->runId !== $data->run_id || !self::text($approval->approvalId, 128, TRUE) ||
        !self::text($approval->toolCallId, 256, TRUE) || !self::text($approval->executionId, 256, TRUE) ||
        isset($ids[$approval->approvalId]) || isset($executions[$approval->executionId]) ||
        !self::positive($approval->expiresAt) ||
        (property_exists($approval, 'decidedAt') && !self::positive($approval->decidedAt)) ||
        (property_exists($approval, 'decidedBy') && !self::text($approval->decidedBy, 128, TRUE)) ||
        !in_array($approval->status, ['pending', 'approved', 'denied', 'expired', 'cancelled'], TRUE) ||
        !in_array($approval->executionStatus, ['pending', 'waiting_approval', 'ready', 'executing',
          'completed', 'uncertain', 'cancelled'], TRUE)) {
        self::fail('invalid_approval');
      }
      $proposal = $approval->proposal;
      if (!self::keys($proposal, ['name', 'moduleId', 'moduleVersion', 'sideEffect',
        'requiredRoles', 'input', 'preview'], ['requiredPermissions']) ||
        !self::text($proposal->name, 256, TRUE) || !self::text($proposal->moduleId, 128, TRUE) ||
        !self::positive($proposal->moduleVersion) || $proposal->sideEffect !== 'write' ||
        !self::strings($proposal->requiredRoles) || !$proposal->input instanceof \stdClass ||
        (property_exists($proposal, 'requiredPermissions') && !self::strings($proposal->requiredPermissions))) {
        self::fail('invalid_approval');
      }
      $preview = $proposal->preview;
      if (!self::keys($preview, ['title', 'summary', 'target']) ||
        !self::text($preview->title, 1000, TRUE) || !self::text($preview->summary, 16000) ||
        !self::keys($preview->target, ['type', 'label'], ['id']) ||
        !self::text($preview->target->type, 256, TRUE) || !self::text($preview->target->label, 4000) ||
        (property_exists($preview->target, 'id') && !self::text($preview->target->id, 1024))) {
        self::fail('invalid_approval');
      }
      $ids[$approval->approvalId] = $executions[$approval->executionId] = TRUE;
      $content_offset = $entry->contentOffset;
      $reasoning_offset = $entry->reasoningOffset;
    }
  }

  private static function jsonValue(mixed $value, int $depth = 0): void {
    if ($depth > self::DEPTH) {
      self::fail('limit_exceeded');
    }
    if ($value === NULL || is_bool($value)) {
      return;
    }
    if (is_int($value) || is_float($value)) {
      if (!is_finite((float) $value) ||
        (floor($value) == $value && abs($value) > self::SAFE_INTEGER) ||
        (is_float($value) && $value == 0 && str_starts_with((string) $value, '-'))) {
        self::fail('invalid_json');
      }
      return;
    }
    if (is_string($value)) {
      if (!mb_check_encoding($value, 'UTF-8')) {
        self::fail('invalid_json');
      }
      return;
    }
    if (!$value instanceof \stdClass && (!is_array($value) || !array_is_list($value))) {
      self::fail('invalid_json');
    }
    foreach ($value as $key => $item) {
      self::jsonValue((string) $key, $depth + 1);
      self::jsonValue($item, $depth + 1);
    }
  }

  private static function keys(mixed $value, array $required, array $optional = []): bool {
    if (!$value instanceof \stdClass) {
      return FALSE;
    }
    $keys = array_keys(get_object_vars($value));
    return array_diff($required, $keys) === [] && array_diff($keys, [...$required, ...$optional]) === [];
  }

  private static function every(array $values, callable $test): bool {
    foreach ($values as $value) {
      if (!$test($value)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  private static function uuid(mixed $value): bool {
    return is_string($value) && (bool) preg_match('/^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/iD', $value);
  }

  private static function text(mixed $value, int $max, bool $nonempty = FALSE): bool {
    return is_string($value) && (!$nonempty || !self::blank($value)) && mb_strlen($value, 'UTF-8') <= $max;
  }

  private static function blank(string $value): bool {
    // Match JavaScript trim, including Unicode separators and the byte-order mark.
    return (bool) preg_match('/^[\x{0009}-\x{000D}\x{0020}\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}]*$/uD', $value);
  }

  private static function reviewText(mixed $value, int $max): bool {
    return is_string($value) && !self::blank($value) && self::utf16($value) <= $max;
  }

  private static function utf16(string $value): int {
    return intdiv(strlen(mb_convert_encoding($value, 'UTF-16LE', 'UTF-8')), 2);
  }

  private static function nullableText(mixed $value, int $max): bool {
    return $value === NULL || self::text($value, $max);
  }

  private static function nullableBoolean(mixed $value): bool {
    return $value === NULL || is_bool($value);
  }

  private static function empty(mixed $value): bool {
    return $value === NULL || $value === '';
  }

  private static function nonnegative(mixed $value): bool {
    return (is_int($value) || is_float($value)) && is_finite((float) $value) &&
      floor($value) == $value && $value >= 0 && $value <= self::SAFE_INTEGER;
  }

  private static function positive(mixed $value): bool {
    return self::nonnegative($value) && $value > 0;
  }

  private static function version(mixed $value, int $version): bool {
    return self::nonnegative($value) && $value == $version;
  }

  private static function geometry(mixed $value): bool {
    return self::positive($value) && $value <= 100000;
  }

  private static function strings(mixed $value): bool {
    return is_array($value) && self::every($value, fn($item) => self::text($item, 256, TRUE));
  }

  private static function within(string $value, int $bytes): void {
    if (strlen($value) > $bytes) {
      self::fail('limit_exceeded');
    }
  }

  private static function fail(string $code): never {
    throw new ChatSessionFormatException($code);
  }

}
