<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_billing;

use Drupal\Core\Database\Connection;
use Drupal\xinshi_ai_usage\Service\UsageChargesInterface;
use Drupal\xinshi_credits\CreditValue;

/** Read complete operation totals from the shared ledger, never from supplier estimates. */
final class BillingReports implements UsageChargesInterface {

  public function __construct(private readonly Connection $database, private readonly bool $enabled) {}

  /** {@inheritdoc} */
  public function summarize(string $siteId, array $operationIds, ?string $actorUserId = NULL): array {
    $totals = ['authorized_atoms' => 0, 'held_atoms' => 0, 'charged_atoms' => 0, 'returned_atoms' => 0];
    $pending = []; $operations = []; $seen = [];
    foreach (array_chunk(array_values(array_unique($operationIds)), 500) as $ids) {
      $roots = $this->database->select('ai_billing_continuation', 'c')->fields('c', ['root_operation_id'])
        ->condition('site_id', $siteId)->condition('operation_id', $ids, 'IN')->execute()->fetchCol();
      $ids = array_values(array_unique([...$ids, ...$roots]));
      $children = $this->database->select('ai_billing_continuation', 'c')->fields('c', ['operation_id'])
        ->condition('site_id', $siteId)->condition('root_operation_id', $ids, 'IN')->execute()->fetchCol();
      $ids = array_values(array_unique([...$ids, ...$children]));
      $query = $this->database->select('ai_billing_authorization', 'a');
      $query->join('credit_reservation', 'r', 'r.id = a.reservation_id AND r.site_id = a.site_id');
      $query->leftJoin('ai_billing_continuation', 'c', 'c.operation_id = a.operation_id AND c.site_id = a.site_id');
      $query->addExpression('COALESCE(c.root_operation_id, a.operation_id)', 'root_operation_id');
      $query->addField('a', 'operation_id');
      $query->fields('r', ['state', 'authorized_atoms', 'charged_atoms', 'returned_atoms'])
        ->condition('a.site_id', $siteId)->condition('a.operation_id', $ids, 'IN');
      if ($actorUserId !== NULL) $query->condition('a.user_id', $actorUserId);
      foreach ($query->execute()->fetchAll(\PDO::FETCH_ASSOC) as $row) {
        if (isset($seen[$row['operation_id']])) continue;
        $seen[$row['operation_id']] = TRUE;
        $operations[$row['root_operation_id']] = TRUE;
        foreach (['authorized_atoms', 'charged_atoms', 'returned_atoms'] as $field) {
          $totals[$field] = CreditValue::add($totals[$field], (int) $row[$field]);
        }
        if ($row['state'] === 'held') {
          $pending[$row['root_operation_id']] = TRUE;
          $totals['held_atoms'] = CreditValue::add($totals['held_atoms'], (int) $row['authorized_atoms']);
        }
      }
    }
    if (!$operations && !$this->enabled) {
      return ['status' => 'not_enabled'];
    }
    return ['status' => 'available', 'scope' => 'complete_matching_operations',
      'operations' => count($operations), 'pending_operations' => count($pending)] + array_map(strval(...), $totals);
  }

}
