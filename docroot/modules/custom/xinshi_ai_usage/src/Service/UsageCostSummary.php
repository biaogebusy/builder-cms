<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Service;

/**
 * Summarizes saved supplier valuations without exposing price-book configuration.
 */
final class UsageCostSummary {

  /**
   * Builds a cost estimate from already authorized attempt rows, per currency.
   */
  public static function fromAttempts(array $rows): array {
    $summary = ['basis' => 'supplier_cost', 'totals' => [], 'rated_attempts' => 0,
      'unpriced_attempts' => 0, 'pending_attempts' => 0,
      'customer_key_attempts' => 0, 'not_sent_attempts' => 0];
    $amounts = [];
    foreach ($rows as $row) {
      if (in_array($row['state'], ['prepared', 'in_flight'], TRUE)
        && $row['cost_valuation_state'] === NULL) {
        $summary['pending_attempts']++;
      }
      elseif ($row['dispatch_state'] === 'not_sent') {
        $summary['not_sent_attempts']++;
      }
      elseif ($row['payer'] === 'customer_key' || $row['cost_unpriced_reason'] === 'customer_key') {
        $summary['customer_key_attempts']++;
      }
      elseif (in_array($row['cost_valuation_state'],
        [CostRatingService::STATE_RATED_ESTIMATE, CostRatingService::STATE_RECONCILED], TRUE)
        && $row['cost_total_micros'] !== NULL && $row['cost_currency'] !== NULL) {
        $currency = $row['cost_currency'];
        $amounts[$currency] = self::addDecimal($amounts[$currency] ?? '0', (string) $row['cost_total_micros']);
        $summary['rated_attempts']++;
      }
      else {
        // A finished call without a saved valuation is unknown, never free.
        $summary['unpriced_attempts']++;
      }
    }
    ksort($amounts);
    foreach ($amounts as $currency => $amount) {
      $summary['totals'][] = ['currency' => $currency, 'amount_micros' => $amount];
    }
    return $summary;
  }

  /**
   * Adds non-negative decimal strings without float conversion or integer overflow.
   */
  public static function addDecimal(string $a, string $b): string {
    $a = ltrim($a, '0') ?: '0';
    $b = ltrim($b, '0') ?: '0';
    if (strlen($a) < 18 && strlen($b) < 18) {
      return (string) ((int) $a + (int) $b);
    }
    $out = '';
    $carry = 0;
    for ($i = strlen($a) - 1, $j = strlen($b) - 1; $i >= 0 || $j >= 0 || $carry; $i--, $j--) {
      $sum = $carry + ($i >= 0 ? (int) $a[$i] : 0) + ($j >= 0 ? (int) $b[$j] : 0);
      $out = ($sum % 10) . $out;
      $carry = intdiv($sum, 10);
    }
    return $out;
  }

}
