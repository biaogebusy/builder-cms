<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai_usage\Unit;

use Drupal\xinshi_ai_usage\Service\UsageCostSummary;
use PHPUnit\Framework\TestCase;

/**
 * Estimates retain currency, precision and missing-valuation distinctions.
 */
final class UsageCostSummaryTest extends TestCase {

  public function testMixedCurrenciesFailuresAndUnpricedCallsRemainDistinct(): void {
    $base = ['state' => 'succeeded', 'dispatch_state' => 'sent', 'payer' => 'platform',
      'cost_valuation_state' => 'rated_estimate', 'cost_unpriced_reason' => NULL,
      'cost_currency' => 'CNY', 'cost_total_micros' => '9000000000000000000'];
    $rows = [$base, ['state' => 'failed', 'billing_role' => 'auxiliary'] + $base,
      ['cost_currency' => 'USD', 'cost_total_micros' => '0', 'cost_valuation_state' => 'reconciled'] + $base,
      ['cost_valuation_state' => 'unpriced', 'cost_unpriced_reason' => 'unknown_model',
        'cost_total_micros' => NULL] + $base,
      ['state' => 'unknown', 'dispatch_state' => 'unknown', 'cost_valuation_state' => NULL] + $base,
      ['state' => 'in_flight', 'cost_valuation_state' => NULL] + $base,
      ['payer' => 'customer_key'] + $base,
      ['state' => 'not_sent', 'dispatch_state' => 'not_sent'] + $base];
    $summary = UsageCostSummary::fromAttempts($rows);
    $this->assertSame([['currency' => 'CNY', 'amount_micros' => '18000000000000000000'],
      ['currency' => 'USD', 'amount_micros' => '0']], $summary['totals']);
    $this->assertSame(['rated_attempts' => 3, 'unpriced_attempts' => 2, 'pending_attempts' => 1,
      'customer_key_attempts' => 1, 'not_sent_attempts' => 1],
      array_diff_key($summary, array_flip(['basis', 'totals'])));
    $this->assertSame('supplier_cost', $summary['basis']);
  }

}
