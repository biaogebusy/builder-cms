<?php

declare(strict_types=1);

namespace Drupal\acquia_connector\Commands;

// The Drush 13 has removed the drush.services.yml for sanitize command.
if (interface_exists('\Drush\Commands\sql\sanitize\SanitizePluginInterface')) {
  class_alias('\Drush\Commands\sql\sanitize\SanitizePluginInterface', '\Drush\Drupal\Commands\sql\SanitizePluginInterface');
}

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Core\State\StateInterface;
use Drush\Commands\DrushCommands;
use Drush\Drupal\Commands\sql\SanitizePluginInterface;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Acquia Connector integration to SQL sanitize for Drush.
 */
final class SqlSanitizeCommands extends DrushCommands implements SanitizePluginInterface {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private StateInterface $state;

  /**
   * Constructs a new SqlSanitizeCommands object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(StateInterface $state) {
    parent::__construct();
    $this->state = $state;
  }

  /**
   * Removes Acquia Connector information from the database.
   *
   * {@inheritdoc}
   *
   * @hook post-command sql-sanitize
   */
  public function sanitize($result, CommandData $commandData): void {
    // Also removes any legacy state key data.
    $this->state->deleteMultiple([
      'acquia_subscription_data',
      'acquia_connector.subscription_data',
      'acquia_connector.key',
      'acquia_connector.identifier',
      'acquia_connector.application_uuid',
    ]);
    $this->logger()->success(dt('Removed Acquia Connector Keys.'));
  }

  /**
   * {@inheritdoc}
   *
   * @hook on-event sql-sanitize-confirms
   */
  public function messages(&$messages, InputInterface $input): void {
    $messages[] = dt('Remove Acquia Connector Keys.');
  }

}
