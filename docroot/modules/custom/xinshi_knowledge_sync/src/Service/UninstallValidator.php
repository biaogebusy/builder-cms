<?php

declare(strict_types=1);

namespace Drupal\xinshi_knowledge_sync\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleUninstallValidatorInterface;

/** Prevents removal of access enforcement while synchronized documents remain. */
final class UninstallValidator implements ModuleUninstallValidatorInterface {

  public function __construct(private readonly Connection $database) {}

  /** {@inheritdoc} */
  public function validate($module): array {
    if ($module === 'xinshi_knowledge_sync' && $this->database->schema()->tableExists(SourceAccess::TABLE) &&
        $this->database->select(SourceAccess::TABLE, 'd')->range(0, 1)->countQuery()->execute()->fetchField()) {
      return [t('Migrate or remove synchronized documents and their ownership records before uninstalling access enforcement.')];
    }
    return [];
  }

}
