<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_billing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\xinshi_ai_usage\Service\UsageChargesInterface;
use Drupal\xinshi_credits\CreditValue;

/** Read complete operation totals from the shared ledger, never from supplier estimates. */
final class BillingReports implements UsageChargesInterface {

  public function __construct(private readonly Connection $database, private readonly ConfigFactoryInterface $config) {}

  /** {@inheritdoc} */
  public function summarize(string $siteId, array $operationIds, ?string $actorUserId = NULL): array {
    $totals = ['authorized_atoms' => 0, 'held_atoms' => 0, 'charged_atoms' => 0, 'returned_atoms' => 0];
    $pending = 0; $operations = 0;
    foreach (array_chunk(array_values(array_unique($operationIds)), 500) as $ids) {
      $query = $this->database->select('ai_billing_authorization', 'a');
      $query->join('credit_reservation', 'r', 'r.id = a.reservation_id AND r.site_id = a.site_id');
      $query->fields('r', ['state', 'authorized_atoms', 'charged_atoms', 'returned_atoms'])
        ->condition('a.site_id', $siteId)->condition('a.operation_id', $ids, 'IN');
      if ($actorUserId !== NULL) $query->condition('a.user_id', $actorUserId);
      foreach ($query->execute()->fetchAll(\PDO::FETCH_ASSOC) as $row) {
        $operations++;
        foreach (['authorized_atoms', 'charged_atoms', 'returned_atoms'] as $field) {
          $totals[$field] = CreditValue::add($totals[$field], (int) $row[$field]);
        }
        if ($row['state'] === 'held') {
          $pending++;
          $totals['held_atoms'] = CreditValue::add($totals['held_atoms'], (int) $row['authorized_atoms']);
        }
      }
    }
    if (!$operations && $this->config->get('xinshi_ai_billing.settings')->get('enabled') !== TRUE) {
      return ['status' => 'not_enabled'];
    }
    return ['status' => 'available', 'scope' => 'complete_matching_operations',
      'operations' => $operations, 'pending_operations' => $pending] + array_map(strval(...), $totals);
  }

}
