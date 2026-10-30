<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_billing;

use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleUninstallValidatorInterface;

/** Retain authorization and price evidence even after admissions are disabled. */
final class BillingUninstallValidator implements ModuleUninstallValidatorInterface {

  public function __construct(private readonly Connection $database) {}

  /** {@inheritdoc} */
  public function validate($module): array {
    if ($module !== 'xinshi_ai_billing') return [];
    foreach (['profile', 'quote', 'authorization', 'permit', 'evidence'] as $suffix) {
      $table = 'ai_billing_' . $suffix;
      if ($this->database->schema()->tableExists($table)
        && $this->database->select($table, 'b')->countQuery()->execute()->fetchField()) {
        return [t('AI billing records must be retained. Disable new billing instead.')];
      }
    }
    return [];
  }

}
