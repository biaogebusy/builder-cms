<?php

declare(strict_types=1);

function budgetInput(string $ref, string $category, string $cap = '10000', string $headroom = '1000'): array {
  return ['publication_ref' => $ref, 'category' => $category, 'currency' => 'CNY',
    'period_start' => time() - 60, 'period_end' => time() + 86400, 'cap_micros' => $cap,
    'execution_headroom_micros' => $headroom, 'source_ref' => 'Synthetic approved budget'];
}

function allocationInput(string $ref, array $ids, string $cap = '2000', string $amount = '1000', string $rate = '1000'): array {
  return ['publication_ref' => $ref, 'rule_id' => $ref, 'rule_version' => 'v1',
    'period_start' => time(), 'period_end' => time() + 7200, 'expires_at' => time() + 3600,
    'cap_atoms' => $cap, 'grant_limit_atoms' => $amount, 'mode' => 'fixed', 'source_kind' => 'admin_gift',
    'source_ref' => 'Synthetic approved allocation',
    'bounds' => array_map(static fn(string $id): array => ['bucket_id' => $id, 'micros_per_point' => $rate,
      'source_ref' => 'Synthetic settlement cost bound'], $ids)];
}
