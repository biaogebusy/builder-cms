<?php

declare(strict_types=1);

namespace Drupal\xinshi_credits;

use Drupal\Core\Config\ConfigFactoryInterface;

/** Resolves the ledger site independently of optional AI producer registration. */
final class CreditContext {

  public function __construct(private readonly ConfigFactoryInterface $config) {}

  /** Require explicit configuration; installing the module does not enable grants. */
  public function site(): string {
    $site = $this->config->get('xinshi_credits.settings')->get('site_id');
    if (!is_string($site) || $site === '') throw new CreditException('credits_not_configured', 503);
    return CreditValue::id($site);
  }

  /** Pause new admission without blocking historical receipts or account reads. */
  public function assertGrantsEnabled(): void {
    if ($this->config->get('xinshi_credits.settings')->get('grants_enabled') !== TRUE) {
      throw new CreditException('credits_grants_disabled', 503);
    }
  }

}
