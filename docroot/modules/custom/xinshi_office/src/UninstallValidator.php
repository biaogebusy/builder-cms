<?php

declare(strict_types=1);

namespace Drupal\xinshi_office;

use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ConfigImportModuleUninstallValidatorInterface;

/** Prevents tool retirement from deleting document, file or reconciliation storage. */
final class UninstallValidator implements ConfigImportModuleUninstallValidatorInterface {

  public function __construct(private readonly Connection $database) {}

  /** {@inheritdoc} */
  public function validate($module): array {
    if ($module === 'xinshi_office') {
      foreach (['document', 'revision', 'file', 'operation'] as $suffix) {
        $table = 'xinshi_office_' . $suffix;
        if ($this->database->schema()->tableExists($table) &&
          $this->database->select($table, 'o')->range(0, 1)->countQuery()->execute()->fetchField()) {
          return [t('Office storage contains documents, resources or receipts. Disable document writes instead of uninstalling the module.')];
        }
      }
    }
    return [];
  }

  /** {@inheritdoc} */
  public function validateConfigImport(string $module, StorageInterface $source_storage): array {
    return $this->validate($module);
  }

}

