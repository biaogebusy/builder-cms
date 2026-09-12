<?php

declare(strict_types=1);

namespace Drupal\config_ignore\Drush\Listeners;

use Drupal\Core\Site\Settings;
use Drush\Commands\config\ConfigExportCommands;
use Drush\Commands\config\ConfigImportCommands;
use Drush\Commands\DrushCommands;
use Drush\Event\ConsoleDefinitionsEvent;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Additional config import/export option.
 *
 * Drush >=13.7.
 */
#[AsEventListener(method: 'optionSetConfig')]
#[AsEventListener(method: 'preConfigAction')]
class ConfigIgnoreListener extends DrushCommands {

  /**
   * Add new flag for config:import and config:export commands.
   */
  public function optionSetConfig(ConsoleDefinitionsEvent $event): void {
    /** @var \Consolidation\AnnotatedCommand\AnnotatedCommand $command */
    $command = $event->getApplication()->get(ConfigExportCommands::EXPORT);
    $command->addOption(name: 'deactivate-config-ignore', description: 'Deactivate config ignore.');
    $command->addUsage('drush config:export --deactivate-config-ignore');
    $command->addUsageOrExample('drush config:export --deactivate-config-ignore', 'Temporarily deactivate config ignore and export full configuration. Use with care.');
    /** @var \Consolidation\AnnotatedCommand\AnnotatedCommand $command */
    $command = $event->getApplication()->get(ConfigImportCommands::IMPORT);
    $command->addOption(name: 'deactivate-config-ignore', description: 'Deactivate config ignore.');
    $command->addUsageOrExample('drush config:import --deactivate-config-ignore', 'Temporarily deactivate config ignore and import full configuration. Use with care.');
  }

  /**
   * Deactivate config_ignore on config export/import.
   */
  public function preConfigAction(ConsoleCommandEvent $event): void {
    $command = $event->getCommand();
    if (!in_array($command?->getName(), [ConfigExportCommands::EXPORT, ConfigImportCommands::IMPORT])) {
      return;
    }
    $deactivate_config_ignore = $event->getInput()->getOption('deactivate-config-ignore');
    if (!empty($deactivate_config_ignore)) {
      // Deactivate on runtime with settings option.
      $settings = Settings::getAll();
      $settings['config_ignore_deactivate'] = TRUE;
      $this->logger()?->notice('Deactivating config ignore.');
      new Settings($settings);
    }
  }

}
