<?php

declare(strict_types=1);

namespace Drupal\xinshi_chat;

use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Extension\ConfigImportModuleUninstallValidatorInterface;
use Drupal\xinshi_chat\Schema\Manager;

/** Keeps Chat structure and access enforcement installed while conversations remain. */
final class UninstallValidator implements ConfigImportModuleUninstallValidatorInterface {

  public function __construct(private readonly Manager $schema) {}

  /** {@inheritdoc} */
  public function validate($module): array {
    return $module === 'xinshi_chat' && $this->schema->hasContent()
      ? [t('Chat contains conversations or messages. Disable Chat instead of uninstalling it.')]
      : [];
  }

  /** {@inheritdoc} */
  public function validateConfigImport(string $module, StorageInterface $source_storage): array {
    return $this->validate($module);
  }

}
