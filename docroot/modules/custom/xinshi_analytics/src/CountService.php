<?php

declare(strict_types=1);

namespace Drupal\xinshi_analytics;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Drupal\xinshi_analytics\Entity\AnalyticsDataset;

/** Counts current, authorized entities; no cached results or arbitrary query paths. */
final class CountService {

  /** Constructs the service using the authenticated request's current account. */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypes,
    private readonly DatasetValidator $validator,
    private readonly AccountProxyInterface $currentUser,
    private readonly AccountSwitcherInterface $accountSwitcher,
    private readonly EvidenceEpochs $epochs,
    private readonly NodeCountDatasets $nodeCounts,
  ) {}

  /** Maps the effective request permission without exposing dataset grants or roles. */
  public function capabilities(): array {
    return $this->authorized(fn(AccountInterface $account): array => [
      'uid' => (string) $account->id(),
      'permissions' => $account->hasPermission('query analytics datasets') ? ['analytics.query'] : [],
    ], FALSE);
  }

  /** Returns only authorized public metadata, with no physical field mappings. */
  public function discover(string $language): array {
    if (!CountQuery::language($language)) {
      throw new AnalyticsException('invalid_query');
    }
    return $this->authorized(function (AccountInterface $account) use ($language): array {
      $datasets = [];
      $storage = $this->entityTypes->getStorage('analytics_dataset');
      $storage->resetCache();
      $automatic = $this->nodeCounts->load($account);
      $configured = $storage->loadMultiple();
      foreach (array_merge($configured, $automatic) as $id => $dataset) {
        // Ambiguous existing configuration never chooses a less restrictive source policy.
        if (isset($configured[$id], $automatic[$id])) {
          continue;
        }
        $isAutomatic = isset($automatic[$id]);
        try {
          $this->available($dataset, $account, $language, $isAutomatic);
        }
        catch (AnalyticsException) {
          continue;
        }
        $policy = $dataset->get('query_policy');
        $dimensions = $filters = [];
        foreach ($dataset->get('dimensions') as $id => $dimension) {
          $dimensions[] = ['id' => $id, 'label' => $dimension['label'], 'kind' => $dimension['kind']];
        }
        foreach ($dataset->get('filters') as $id => $filter) {
          $filters[] = ['id' => $id, 'label' => $filter['label'], 'operators' => $filter['operators'],
            'values' => $filter['values']];
        }
        $datasets[] = ['id' => $dataset->id(), 'version' => $dataset->get('version'),
          'label' => $dataset->label(), 'dimensions' => $dimensions, 'filters' => $filters,
          'timezone' => $policy['timezone'], 'languages' => $policy['languages'],
          'limits' => ['maxCalendarMonths' => $policy['max_calendar_months'],
            'maxScannedEntities' => $policy['max_scanned_entities'], 'maxGroups' => $policy['max_groups']]];
        $datasets[array_key_last($datasets)]['scopes'] = ['all', 'range'];
        $datasets[array_key_last($datasets)]['timeBasis'] = $isAutomatic ? 'created' : 'configured';
        if (count($datasets) > 100) {
          throw new AnalyticsException('query_failed');
        }
      }
      return $this->bounded(['datasets' => $datasets]);
    });
  }

  /** Executes one complete query, preserving the caller's exact public intent. */
  public function count(array $query): array {
    return $this->evaluate($query, FALSE)['result'];
  }

  /** Internal evidence snapshot; fingerprints and marker names never enter the public result. */
  public function snapshot(array $query): array {
    return $this->evaluate($query, TRUE);
  }

  /** Uses the same authorization, limits and query semantics for counts and evidence. */
  private function evaluate(array $query, bool $evidence): array {
    CountQuery::validate($query);
    $identityBefore = $evidence ? $this->epochs->identity((string) $this->currentUser->id()) : [];
    return $this->authorized(function (AccountInterface $account) use ($query, $evidence, $identityBefore): array {
      $storage = $this->entityTypes->getStorage('analytics_dataset');
      $storage->resetCache([$query['datasetId']]);
      $configured = $storage->load($query['datasetId']);
      $automatic = $this->nodeCounts->load($account, $query['datasetId'])[$query['datasetId']] ?? NULL;
      if ($configured && $automatic) {
        throw new AnalyticsException('dataset_unavailable');
      }
      $isAutomatic = $automatic !== NULL;
      $dataset = $configured ?? $automatic;
      // Read markers before validating access or configuration to detect concurrent changes.
      $before = $evidence && $dataset ? $this->epochs->current($dataset, (string) $account->id()) : [];
      $this->available($dataset, $account, $query['language'], $isAutomatic);
      if ($evidence && array_intersect_key($before, $identityBefore) !== $identityBefore) {
        throw new AnalyticsException('query_failed');
      }
      if ($query['datasetVersion'] !== $dataset->get('version')) {
        throw new AnalyticsException('dataset_version_changed');
      }
      $policy = $dataset->get('query_policy');
      $dimensions = $dataset->get('dimensions');
      $filters = $dataset->get('filters');
      if ($query['timezone'] !== $policy['timezone'] || array_diff($query['dimensions'], array_keys($dimensions))) {
        throw new AnalyticsException('invalid_query');
      }
      foreach ($query['filters'] as $filter) {
        $allowed = $filters[$filter['field']] ?? NULL;
        $values = $filter['operator'] === 'eq' ? [$filter['value']] : $filter['values'];
        if (!$allowed || !in_array($filter['operator'], $allowed['operators'], TRUE)
          || array_diff($values, $allowed['values'])) {
          throw new AnalyticsException('invalid_query');
        }
      }
      $zone = new \DateTimeZone($query['timezone']);
      $scope = $query['scope'];
      $from = $until = NULL;
      if ($scope['kind'] === 'range') {
        $from = CountQuery::instant($scope['from'])->setTimezone($zone);
        $until = CountQuery::instant($scope['toExclusive'])->setTimezone($zone);
        $last = $until->modify('-1 second');
        $months = ((int) $last->format('Y') - (int) $from->format('Y')) * 12
          + (int) $last->format('n') - (int) $from->format('n') + 1;
        if ($months > $policy['max_calendar_months']) {
          throw new AnalyticsException('range_too_large');
        }
      }
      $started = gmdate('Y-m-d\TH:i:s\Z');
      $fingerprint = $evidence ? hash_init('sha256') : NULL;
      $rows = $this->scan($dataset, $query, $account, $from, $until, $zone, $isAutomatic, $fingerprint);
      $result = $this->bounded(['query' => $query, 'rows' => $rows, 'completeness' => 'complete',
        'startedAt' => $started, 'finishedAt' => gmdate('Y-m-d\TH:i:s\Z')]);
      if ($evidence && $before !== $this->epochs->current($dataset, (string) $account->id())) {
        throw new AnalyticsException('query_failed');
      }
      return ['result' => $result, 'uid' => (string) $account->id(), 'epochs' => $before,
        'definition' => $evidence ? $dataset->toArray() : [],
        'fingerprint' => $fingerprint ? hash_final($fingerprint) : NULL];
    });
  }

  /** Reloads the account and restores the outer context even when querying fails. */
  private function authorized(callable $callback, bool $requireQuery = TRUE): array {
    $switched = FALSE;
    try {
      $users = $this->entityTypes->getStorage('user');
      $id = $this->currentUser->id();
      $users->resetCache([$id]);
      $account = $id ? $users->load($id) : NULL;
      if (!$account instanceof UserInterface || !$account->isActive()) {
        throw new AnalyticsException('dataset_unavailable');
      }
      // Refresh ordinary users, but retain decorated principals and their scope policies.
      // Replacing an OAuth principal with its full user would widen field and query access.
      $principal = $this->currentUser->getAccount();
      $effective = $principal instanceof User ? $account : $principal;
      if ($requireQuery && (!$account->hasPermission('query analytics datasets')
        || !$effective->hasPermission('query analytics datasets'))) {
        throw new AnalyticsException('dataset_unavailable');
      }
      $this->accountSwitcher->switchTo($effective);
      $switched = TRUE;
      return $callback($effective);
    }
    catch (AnalyticsException $error) {
      throw $error;
    }
    catch (\Throwable) {
      throw new AnalyticsException('query_failed');
    }
    finally {
      if ($switched) {
        $this->accountSwitcher->switchBack();
      }
    }
  }

  /** Definition-level field access is checked before exposing capabilities. */
  private function available(?AnalyticsDataset $dataset, AccountInterface $account, string $language, bool $isAutomatic): void {
    if (!$dataset || !$dataset->status()
      || (!$isAutomatic && (str_starts_with($dataset->id(), 'node__')
        || !$account->hasPermission($dataset->queryPermission())))) {
      throw new AnalyticsException('dataset_unavailable');
    }
    $definitions = $this->validator->validate($dataset);
    if (!in_array($language, $dataset->get('query_policy')['languages'], TRUE)) {
      throw new AnalyticsException('dataset_unavailable');
    }
    $type = $this->entityTypes->getDefinition($dataset->get('entity_type'));
    $names = [$dataset->get('time_field'), $type->getKey('published')];
    foreach (['dimensions', 'filters'] as $group) {
      foreach ($dataset->get($group) as $mapping) {
        $names[] = $mapping['field'];
      }
    }
    $access = $this->entityTypes->getAccessControlHandler($type->id());
    foreach ($definitions as $fields) {
      foreach (array_unique($names) as $name) {
        if (!$access->fieldAccess('view', $fields[$name], $account)) {
          throw new AnalyticsException('dataset_unavailable');
        }
      }
    }
  }

  /** Scans bounded, access-filtered IDs before reading any time or filter value. */
  private function scan(AnalyticsDataset $dataset, array $query, AccountInterface $account,
    ?\DateTimeImmutable $from, ?\DateTimeImmutable $until, \DateTimeZone $zone, bool $isAutomatic,
    ?\HashContext $fingerprint = NULL): array {
    $type = $this->entityTypes->getDefinition($dataset->get('entity_type'));
    $storage = $this->entityTypes->getStorage($type->id());
    $policy = $dataset->get('query_policy');
    $timeField = $dataset->get('time_field');
    $dimensions = $dataset->get('dimensions');
    $filters = $dataset->get('filters');
    $required = [$timeField, $type->getKey('published')];
    foreach ($query['dimensions'] as $alias) {
      $required[] = $dimensions[$alias]['field'];
    }
    foreach ($query['filters'] as $filter) {
      $required[] = $filters[$filter['field']]['field'];
    }
    $rows = [];
    $scanned = $cursor = 0;
    $deadline = hrtime(TRUE) + $policy['max_seconds'] * 1_000_000_000;
    do {
      if (hrtime(TRUE) >= $deadline) {
        throw new AnalyticsException('query_failed');
      }
      $select = $storage->getQuery()->accessCheck(TRUE)
        ->condition($type->getKey('id'), $cursor, '>')->sort($type->getKey('id'))
        ->range(0, min(100, $policy['max_scanned_entities'] - $scanned + 1));
      if ($bundleKey = $type->getKey('bundle')) {
        $select->condition($bundleKey, $dataset->get('bundles'), 'IN');
      }
      $ids = array_values($select->execute());
      if (hrtime(TRUE) >= $deadline) {
        throw new AnalyticsException('query_failed');
      }
      if ($scanned + count($ids) > $policy['max_scanned_entities']) {
        throw new AnalyticsException('range_too_large');
      }
      $scanned += count($ids);
      // Avoid serving stale field values from an earlier service call in this request.
      $storage->resetCache($ids);
      $entities = $ids ? $storage->loadMultiple($ids) : [];
      foreach ($ids as $id) {
        if ((int) $id <= $cursor || hrtime(TRUE) >= $deadline) {
          throw new AnalyticsException('query_failed');
        }
        $cursor = (int) $id;
        $entity = $entities[$id] ?? NULL;
        if (!$entity || !in_array($entity->bundle(), $dataset->get('bundles'), TRUE)
          || !$entity->hasTranslation($query['language'])) {
          continue;
        }
        $entity = $entity->getTranslation($query['language']);
        if (!$entity->access('view', $account)) {
          continue;
        }
        foreach (array_unique($required) as $name) {
          if (!$entity->get($name)->access('view', $account)) {
            continue 2;
          }
        }
        if ((!$isAutomatic && !$entity->isPublished())
          || ($from !== NULL && $entity->get($timeField)->isEmpty())) {
          continue;
        }
        $timestamp = (int) $entity->get($timeField)->value;
        if ($from !== NULL && ($timestamp < $from->getTimestamp() || $timestamp >= $until->getTimestamp())) {
          continue;
        }
        foreach ($query['filters'] as $filter) {
          $value = $this->category($entity->get($filters[$filter['field']]['field']));
          $values = $filter['operator'] === 'eq' ? [$filter['value']] : $filter['values'];
          if (!in_array($value, $values, TRUE)) {
            continue 2;
          }
        }
        if ($fingerprint) {
          $values = [];
          foreach (array_unique($required) as $name) {
            $values[$name] = $entity->get($name)->getValue();
          }
          // Include identity and participating values, not merely aggregate totals.
          hash_update($fingerprint, json_encode([$entity->getEntityTypeId(), $entity->id(),
            $entity->uuid(), $query['language'], $values], JSON_THROW_ON_ERROR) . "\n");
        }
        $keys = [];
        foreach ($query['dimensions'] as $alias) {
          $dimension = $dimensions[$alias];
          $keys[] = $dimension['kind'] === 'month'
            ? ($entity->get($timeField)->isEmpty() ? NULL
              : (new \DateTimeImmutable('@' . $timestamp))->setTimezone($zone)->format('Y-m'))
            : $this->category($entity->get($dimension['field']));
        }
        $key = json_encode($keys, JSON_THROW_ON_ERROR);
        $rows[$key] ??= ['dimensions' => $keys, 'count' => 0];
        $rows[$key]['count']++;
        if (count($rows) > $policy['max_groups']) {
          throw new AnalyticsException('range_too_large');
        }
      }
    } while ($ids);
    ksort($rows, SORT_STRING);
    return !$query['dimensions'] && !$rows ? [['dimensions' => [], 'count' => 0]] : array_values($rows);
  }

  /** One scalar field item yields one public string or a distinct null bucket. */
  private function category(\Drupal\Core\Field\FieldItemListInterface $field): ?string {
    if ($field->isEmpty()) {
      return NULL;
    }
    $property = $field->getFieldDefinition()->getFieldStorageDefinition()->getMainPropertyName();
    $raw = $field->first()->get($property)->getCastedValue();
    $value = is_bool($raw) ? ($raw ? '1' : '0') : (string) $raw;
    if (!CountQuery::text($value)) {
      throw new AnalyticsException('query_failed');
    }
    return $value;
  }

  /** Never return truncated discovery or a partial table as complete. */
  private function bounded(array $result): array {
    if (strlen(json_encode($result, JSON_THROW_ON_ERROR)) > 1024 * 1024) {
      throw new AnalyticsException('query_failed');
    }
    return $result;
  }

}
