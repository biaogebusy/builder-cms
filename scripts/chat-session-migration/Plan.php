<?php

declare(strict_types=1);

namespace Xinshi\ChatMigration;

use Drupal\xinshi_ai\Service\ChatSessionFields;
use Drupal\xinshi_ai_reference\ChatSessionFormat;
use Drupal\xinshi_ai_reference\ChatSessionSnapshot;

/** Binds prepared fields back to every byte of the frozen entity and revision archive. */
final class Plan {

  public readonly array $manifest;
  public readonly array $report;
  private readonly string $export;
  private readonly string $proposals;
  private array $preserved = [];

  public function __construct(private readonly Source $source, string $export, string $proposals) {
    $this->export = Archive::directory($export);
    $this->proposals = Archive::directory($proposals);
    $this->manifest = Archive::manifest($this->export . '/manifest.json');
    $this->report = Archive::manifest($this->proposals . '/report.json');
    $manifest = $this->manifest;
    $report = $this->report;
    $plan_batch = $source->targetVersion === 3;
    if (($manifest['version'] ?? NULL) !== 1 ||
      ($manifest['operation'] ?? NULL) !== ($plan_batch ? 'cms-chat-plan-export' : 'cms-chat-export') ||
      ($manifest['complete'] ?? NULL) !== TRUE || ($manifest['site'] ?? NULL) !== $source->site ||
      ($report['version'] ?? NULL) !== 1 ||
      ($report['operation'] ?? NULL) !== ($plan_batch ? 'plan-proposal-only' : 'proposal-only') ||
      ($report['complete'] ?? NULL) !== TRUE || ($report['counts']['exceptions'] ?? NULL) !== 0 ||
      ($report['inputSha256'] ?? NULL) !== ($manifest['inputSha256'] ?? '') ||
      ($report['counts']['converted'] ?? NULL) !== ($manifest['counts']['snapshots'] ?? -1) ||
      ($report['counts']['records'] ?? NULL) !== ($report['counts']['converted'] ?? -1)) {
      throw new \RuntimeException('incomplete_or_mismatched_plan');
    }
    foreach ([$manifest['entitiesSha256'] ?? NULL, $manifest['inputSha256'] ?? NULL,
      $report['proposalsSha256'] ?? NULL] as $digest) {
      if (!is_string($digest) || !preg_match('/^[a-f0-9]{64}$/D', $digest)) {
        throw new \RuntimeException('invalid_plan_digest');
      }
    }
    $missing = [];
    foreach ($manifest['bindings'] as $nid => $_chats) {
      if (($manifest['nodes'][$nid]['bundle'] ?? NULL) !== 'ai_session') {
        $missing[] = (string) $nid;
      }
    }
    if ($missing !== ($manifest['missingTargets'] ?? NULL)) {
      throw new \RuntimeException('missing_target_manifest_mismatch');
    }
    // Exhaust every iterator before any write so a corrupt final line cannot create a partial batch.
    foreach (['entities.ndjson' => $manifest['entitiesSha256'], 'sessions.ndjson' => $manifest['inputSha256']] as $name => $hash) {
      foreach (Archive::lines($this->export . '/' . $name, $hash) as $_line) {}
    }
    foreach (Archive::lines($this->proposals . '/proposals.ndjson', $report['proposalsSha256']) as $_line) {}
    if (isset($report['preservedExceptions'])) {
      if (!$plan_batch) {
        throw new \RuntimeException('invalid_plan_exceptions');
      }
      $path = $this->proposals . '/plan-exceptions.json';
      $accepted = Archive::manifest($path);
      ChatSessionFormat::decode(file_get_contents($path));
      $summary = $report['preservedExceptions'];
      if (!is_array($summary) || ($accepted['version'] ?? NULL) !== 1 ||
        ($accepted['operation'] ?? NULL) !== 'preserve-plan-exceptions' ||
        ($accepted['inputSha256'] ?? NULL) !== $manifest['inputSha256'] ||
        array_diff(array_keys($accepted), ['version', 'operation', 'inputSha256', 'records']) ||
        !is_array($accepted['records'] ?? NULL) || !array_is_list($accepted['records']) ||
        !$accepted['records'] || ($summary['manifestSha256'] ?? NULL) !== hash_file('sha256', $path)) {
        throw new \RuntimeException('invalid_plan_exceptions');
      }
      $reasons = [];
      foreach ($accepted['records'] as $entry) {
        if (!is_array($entry) || count($entry) !== 2 ||
          !is_string($entry['sourceSha256'] ?? NULL) ||
          !preg_match('/^[a-f0-9]{64}$/D', $entry['sourceSha256']) ||
          !in_array($entry['code'] ?? NULL, ['invalid_plan', 'unmapped_error_flag'], TRUE) ||
          isset($this->preserved[$entry['sourceSha256']])) {
          throw new \RuntimeException('invalid_plan_exceptions');
        }
        $this->preserved[$entry['sourceSha256']] = $entry['code'];
        $reasons[$entry['code']] = ($reasons[$entry['code']] ?? 0) + 1;
      }
      if (($summary['count'] ?? NULL) !== count($this->preserved) ||
        !self::same((object) ($summary['reasons'] ?? []), (object) $reasons)) {
        throw new \RuntimeException('invalid_plan_exceptions');
      }
    }
  }

  /** Verify collection membership and source configuration again at each command boundary. */
  public function context(bool $installed = TRUE): void {
    $scan = $this->source->scan($installed);
    $settings = $this->source->settings();
    $activated = $this->manifest['storageConfiguration'];
    $activated['exists'] = TRUE;
    $activated['data']['storage_version'] = $this->source->targetVersion;
    if ($scan['sourceFingerprint'] !== $this->manifest['sourceFingerprint'] ||
      ($settings !== $this->manifest['storageConfiguration'] && $settings !== $activated) ||
      $this->source->layout(FALSE) !== $this->manifest['layout'] ||
      $this->source->inventory() !== $this->manifest['nodes'] ||
      $this->source->bindings($this->manifest['nodes']) !== $this->manifest['bindings']) {
      throw new \RuntimeException('source_scope_changed');
    }
  }

  /** Each yielded pair contains only source bytes or the deterministic field split. */
  public function entities(): \Generator {
    $inputs = Archive::lines($this->export . '/sessions.ndjson', $this->manifest['inputSha256']);
    $proposals = Archive::lines($this->proposals . '/proposals.ndjson', $this->report['proposalsSha256']);
    $seen = [];
    $count = 0;
    $row_counts = [];
    $preserved = [];
    foreach (Archive::lines($this->export . '/entities.ndjson', $this->manifest['entitiesSha256']) as $raw) {
      $record = json_decode($raw, TRUE, 64, JSON_THROW_ON_ERROR);
      $nid = $record['nid'] ?? NULL;
      if (!is_string($nid) || !isset($this->manifest['nodes'][$nid]) || isset($seen[$nid]) ||
        !isset($record['rows']) || !is_array($record['rows'])) {
        throw new \RuntimeException('invalid_entity_archive');
      }
      $seen[$nid] = $this->manifest['nodes'][$nid];
      $before = Archive::unpack($record['rows']);
      if (array_keys($before) !== array_keys($this->source->layout(FALSE)) ||
        ($before['node'][0]['nid'] ?? NULL) !== $nid ||
        ($before['node'][0]['uuid'] ?? NULL) !== $seen[$nid]['uuid'] ||
        ($before['node'][0]['type'] ?? NULL) !== $seen[$nid]['bundle']) {
        throw new \RuntimeException('entity_identity_mismatch');
      }
      foreach ($before as $table => $items) {
        $row_counts[$table] = ($row_counts[$table] ?? 0) + count($items);
      }
      foreach ($this->source->layout() as $table => $_definition) {
        $before[$table] ??= [];
      }
      ksort($before, SORT_STRING);
      $targets = [];
      $contexts = [];
      foreach ($this->source->snapshots($before, $this->manifest['bindings']) as $snapshot) {
        if (!$inputs->valid() || !$proposals->valid() || Archive::json($snapshot) !== $inputs->current()) {
          throw new \RuntimeException('snapshot_set_mismatch');
        }
        $proposal = ChatSessionFormat::decode($proposals->current());
        if (!$proposal instanceof \stdClass || ($proposal->status ?? NULL) !== 'converted' ||
          ($proposal->converterVersion ?? NULL) !== ($this->source->targetVersion === 3 ? 2 : 1) ||
          ($proposal->sourceSha256 ?? NULL) !== hash('sha256', $inputs->current()) ||
          !self::same($proposal->source ?? NULL, (object) $snapshot['source'])) {
          throw new \RuntimeException('proposal_source_mismatch');
        }
        $fields = ChatSessionFormat::validate($proposal->fields ?? NULL,
          $snapshot['source']['entityUuid'], $snapshot['conversationIds']);
        $context = $this->verify($snapshot, $proposal, $proposals->current());
        if (isset($this->preserved[$proposal->sourceSha256])) {
          $preserved[$proposal->sourceSha256] = TRUE;
        }
        $revision = $snapshot['source']['revisionId'];
        $without_title = clone $fields;
        unset($without_title->title);
        if (isset($targets[$revision]) && !self::same($targets[$revision], $without_title)) {
          throw new \RuntimeException('translated_targets_diverged');
        }
        $targets[$revision] = $without_title;
        $contexts[$revision] = $context;
        $inputs->next();
        $proposals->next();
        $count++;
      }
      yield $nid => ['before' => $before, 'after' => $this->targetRows($before, $targets, $contexts)];
    }
    if ($seen !== $this->manifest['nodes'] || count($seen) !== $this->manifest['counts']['entities'] ||
      $row_counts !== $this->manifest['rows'] || $count !== $this->manifest['counts']['snapshots'] ||
      $inputs->valid() || $proposals->valid() || count($preserved) !== count($this->preserved)) {
      throw new \RuntimeException('incomplete_plan_coverage');
    }
  }

  /** Compare JSON objects by keys and arrays by order without PHP's loose scalar equality. */
  public static function same(mixed $left, mixed $right): bool {
    $sort = static function (mixed $value) use (&$sort): mixed {
      if ($value instanceof \stdClass) {
        $items = get_object_vars($value);
        ksort($items, SORT_STRING);
        return (object) array_map($sort, $items);
      }
      return is_array($value) ? array_map($sort, $value) : $value;
    };
    return Archive::json($sort($left)) === Archive::json($sort($right));
  }

  /** Independently constrain all proposal changes; a checksum alone is not permission to edit text. */
  private function verify(array $snapshot, \stdClass $proposal, string $raw): ?string {
    $suffix = ',"targetSha256":"' . ($proposal->targetSha256 ?? '') . '"}';
    $position = strpos($raw, ',"fields":');
    if ($position === FALSE || !str_ends_with($raw, $suffix) ||
      hash('sha256', substr($raw, $position + 10, -strlen($suffix))) !== $proposal->targetSha256) {
      throw new \RuntimeException('target_digest_mismatch');
    }
    if ($this->source->targetVersion === 3) {
      $expected = PlanContent::convert($snapshot['fields'],
        $snapshot['source']['entityUuid'], $snapshot['conversationIds'],
        $this->preserved[$proposal->sourceSha256] ?? NULL);
      if (($proposal->sourceFormat ?? NULL) !== 'split-fields@2' ||
        !self::same($proposal->fields, $expected['fields']) ||
        !self::same($proposal->planSplit ?? NULL, $expected['proof'])) {
        throw new \RuntimeException('plan_split_mismatch');
      }
      return $expected['context'];
    }
    $expected = clone $snapshot['fields'];
    foreach (array_keys(ChatSessionFields::definitions()) as $name) {
      $expected->$name = NULL;
    }
    $expected->storage_version = 2;
    $expected->edit_version = 1;
    $expected->storage_mode = 'inline';
    $expected->knowledge_refs = [];
    $format = 'plain';
    $body = $expected->content?->value;
    if (ChatSessionSnapshot::managedEnvelope($body)) {
      $envelope = ChatSessionFormat::decode($body);
      if (!$envelope instanceof \stdClass || ($envelope->version ?? NULL) !== 1) {
        throw new \RuntimeException('unsupported_source_envelope');
      }
      $allowed = ['kind', 'version', 'content'];
      switch ($envelope->kind) {
        case 'xinshi-protected-run':
          $allowed = ['kind', 'version', 'runId', 'chatId'];
          $expected->storage_mode = 'protected_reference';
          $expected->run_id = $envelope->runId ?? NULL;
          $expected->result_reference = $envelope;
          $expected->content = NULL;
          $format = 'protected-run@1';
          break;

        case 'xinshi-chat-user':
          $allowed = [...$allowed, 'messageId', 'knowledgeIds'];
          if (($envelope->messageId ?? NULL) !== $snapshot['source']['entityUuid'] ||
            !is_array($envelope->knowledgeIds ?? NULL) || !$envelope->knowledgeIds) {
            throw new \RuntimeException('source_identity_mismatch');
          }
          $expected->knowledge_refs = $envelope->knowledgeIds;
          $expected->content = (object) ['value' => $envelope->content ?? NULL, 'format' => $expected->content->format];
          $format = 'chat-user@1';
          break;

        case 'xinshi-chat-run':
          $allowed = [...$allowed, 'runId', 'messageId', 'runCreatedAt', 'isError',
            'outputCompletion', 'uiReview', 'uiReviewRun', 'approvalTimeline'];
          $generation = property_exists($envelope, 'runId');
          if ($generation === property_exists($envelope, 'messageId') ||
            ($envelope->{$generation ? 'runId' : 'messageId'} ?? NULL) !== $snapshot['source']['entityUuid']) {
            throw new \RuntimeException('source_identity_mismatch');
          }
          $expected->run_id = $generation ? $envelope->runId : NULL;
          $expected->content = (object) ['value' => $envelope->content ?? NULL, 'format' => $expected->content->format];
          foreach (['runCreatedAt' => 'run_created_at_ms', 'isError' => 'is_error',
            'outputCompletion' => 'output_completion', 'approvalTimeline' => 'approval_timeline'] as $old => $new) {
            if (property_exists($envelope, $old)) {
              if ($envelope->$old === NULL) {
                throw new \RuntimeException('invalid_source_metadata');
              }
              $expected->$new = $envelope->$old;
            }
          }
          if (property_exists($envelope, 'uiReview') || property_exists($envelope, 'uiReviewRun')) {
            $expected->ui_review = (object) ['version' => 1, 'report' => $envelope->uiReview ?? NULL,
              'lastReviewRun' => $envelope->uiReviewRun ?? NULL];
          }
          $format = 'chat-run@1';
          break;

        default:
          throw new \RuntimeException('unsupported_source_envelope');
      }
      if (array_diff(array_keys(get_object_vars($envelope)), $allowed)) {
        throw new \RuntimeException('unmapped_source_metadata');
      }
    }
    if ($proposal->sourceFormat !== $format || !self::same($expected, $proposal->fields)) {
      throw new \RuntimeException('proposal_changed_unrelated_fields');
    }
    return NULL;
  }

  /** Preserve all base/revision rows and all existing field bytes except the outer content wrapper. */
  private function targetRows(array $rows, array $targets, array $contexts): array {
    if (!$targets) {
      return $rows;
    }
    if ($this->source->targetVersion === 3) {
      return $this->planRows($rows, $targets, $contexts);
    }
    $node = $rows['node'][0];
    $languages = array_column($rows['node_revision'], 'langcode', 'vid');
    foreach ($this->source->layout() as $table => $definition) {
      $field = $definition['field'] ?? NULL;
      if ($field === 'content') {
        $items = [];
        foreach ($rows[$table] as $row) {
          $target = $targets[$row['revision_id']] ?? NULL;
          if ($row['deleted'] !== '0') {
            $items[] = $row;
            continue;
          }
          if (!$target) {
            throw new \RuntimeException('orphan_content_revision');
          }
          if ($target->content !== NULL) {
            $row['content_value'] = $target->content->value;
            $row['content_format'] = $target->content->format;
            $items[] = $row;
          }
        }
        $rows[$table] = $items;
      }
      elseif ($field && isset(ChatSessionFields::definitions()[$field])) {
        $rows[$table] = [];
        foreach ($targets as $vid => $target) {
          if (!$definition['revision'] && (string) $vid !== $node['vid']) {
            continue;
          }
          $values = $field === 'knowledge_refs' ? $target->$field :
            ($target->$field === NULL ? [] : [$target->$field]);
          foreach ($values as $delta => $value) {
            if (in_array($field, ['ui_review', 'approval_timeline', 'result_reference'], TRUE)) {
              $value = Archive::json($value);
            }
            $row = ['bundle' => 'ai_session', 'deleted' => '0', 'entity_id' => $node['nid'],
              'revision_id' => (string) $vid, 'langcode' => $languages[$vid], 'delta' => (string) $delta,
              $definition['columns']['value'] => is_bool($value) ? ($value ? '1' : '0') : (string) $value];
            ksort($row, SORT_STRING);
            $rows[$table][] = $row;
          }
        }
        usort($rows[$table], static function ($a, $b) use ($definition): int {
          foreach ($definition['keys'] as $key) {
            $comparison = $key === 'langcode' ? strcmp($a[$key], $b[$key]) : strnatcmp($a[$key], $b[$key]);
            if ($comparison) {
              return $comparison;
            }
          }
          return 0;
        });
      }
    }
    return $rows;
  }

  /** Keep v2 field bytes, deleted rows and all identities; replace only the independent Plan domains. */
  private function planRows(array $rows, array $targets, array $contexts): array {
    $node = $rows['node'][0];
    $languages = array_column($rows['node_revision'], 'langcode', 'vid');
    foreach ($this->source->layout() as $table => $definition) {
      $field = $definition['field'] ?? NULL;
      if ($field === 'plan_context') {
        $rows[$table] = [];
        foreach ($contexts as $vid => $context) {
          if ($context === NULL || (!$definition['revision'] && (string) $vid !== $node['vid'])) {
            continue;
          }
          $row = ['bundle' => 'ai_session', 'deleted' => '0', 'entity_id' => $node['nid'],
            'revision_id' => (string) $vid, 'langcode' => $languages[$vid], 'delta' => '0',
            $definition['columns']['value'] => $context];
          ksort($row, SORT_STRING);
          $rows[$table][] = $row;
        }
        // Source snapshots may follow language order; physical rows always follow SQL key order.
        usort($rows[$table], static fn($a, $b) => strnatcmp($a['revision_id'], $b['revision_id']));
      }
      elseif (in_array($field, ['content', 'storage_version', 'edit_version', 'approval_timeline'], TRUE)) {
        foreach ($rows[$table] as &$row) {
          if ($row['deleted'] !== '0') {
            continue;
          }
          $target = $targets[$row['revision_id']] ?? NULL;
          if (!$target) {
            throw new \RuntimeException('orphan_plan_revision');
          }
          $column = $definition['columns']['value'];
          $row[$column] = match ($field) {
            'content' => $target->content->value,
            'approval_timeline' => PlanContent::approvalBytes($row[$column], $target->approval_timeline),
            default => (string) $target->$field,
          };
        }
        unset($row);
      }
    }
    return $rows;
  }

}
