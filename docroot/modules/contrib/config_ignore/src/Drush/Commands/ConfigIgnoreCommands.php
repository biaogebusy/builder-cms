<?php

namespace Drupal\config_ignore\Drush\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Core\Site\Settings;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Commands\config\ConfigExportCommands;
use Drush\Commands\config\ConfigImportCommands;

/**
 * Additional config import/export option.
 *
 * Drush <13.7
 */
final class ConfigIgnoreCommands extends DrushCommands {

  /**
   * Additional flag for config:import command.
   */
  #[CLI\Hook(type: HookManager::OPTION_HOOK, target: ConfigExportCommands::EXPORT)]
  #[CLI\Option(name: 'deactivate-config-ignore', description: 'Deactivate config ignore.')]
  #[CLI\Usage(name: 'drush config:export --deactivate-config-ignore', description: 'Temporarily deactivate config ignore and export full configuration. Use with care.')]
  public function optionSetConfigExport():void {
  }

  /**
   * Additional flag for config:export command.
   */
  #[CLI\Hook(type: HookManager::OPTION_HOOK, target: ConfigImportCommands::IMPORT)]
  #[CLI\Option(name: 'deactivate-config-ignore', description: 'Deactivate config ignore.')]
  #[CLI\Usage(name: 'drush config:import --deactivate-config-ignore', description: 'Temporarily deactivate config ignore and import full configuration. Use with care.')]
  public function optionSetConfigImport(): void {
  }

  /**
   * Deactivate config_ignore on config export.
   */
  #[CLI\Hook(type: HookManager::PRE_COMMAND_HOOK, target: ConfigExportCommands::EXPORT)]
  public function preConfigExport(CommandData $commandData): void {
    $this->deactivateConfigIgnore($commandData);
  }

  /**
   * Deactivate config_ignore on config import.
   */
  #[CLI\Hook(type: HookManager::PRE_COMMAND_HOOK, target: ConfigImportCommands::IMPORT)]
  public function preConfigImport(CommandData $commandData): void {
    $this->deactivateConfigIgnore($commandData);
  }

  /**
   * Deactivate Config ignore config transformations.
   *
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   *   Data related to the event.
   */
  protected function deactivateConfigIgnore(CommandData $commandData): void {
    if (class_exists('\Drush\Event\ConsoleDefinitionsEvent')) {
      // Skip if ConsoleDefinitionsEvent already exists,
      // means that we use Drush >=13.7.0.
      return;
    }

    $deactivate_config_ignore = $commandData->input()->getOption('deactivate-config-ignore');
    if (!empty($deactivate_config_ignore)) {
      // Deactivate on runtime with settings option.
      $settings = Settings::getAll();
      $settings['config_ignore_deactivate'] = TRUE;
      $this->logger()?->notice('Deactivating config ignore.');
      new Settings($settings);
    }
  }

}
