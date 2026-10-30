<?php

declare(strict_types=1);

namespace Drupal\xinshi_credits;

use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleUninstallValidatorInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/** Prevent routine module removal from deleting committed ledger history. */
final class CreditUninstallValidator implements ModuleUninstallValidatorInterface {

  public function __construct(private readonly Connection $database) {}

  /** {@inheritdoc} */
  public function validate($module): array {
    if ($module === 'xinshi_credits' && $this->database->schema()->tableExists('credit_journal')
      && $this->database->select('credit_journal', 'j')->fields('j', ['id'])->range(0, 1)->execute()->fetchField()) {
      return [new TranslatableMarkup('Credit ledger records must be retained')];
    }
    return [];
  }

}
