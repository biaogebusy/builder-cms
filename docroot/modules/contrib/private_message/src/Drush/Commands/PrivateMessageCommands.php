<?php

declare(strict_types=1);

namespace Drupal\private_message\Drush\Commands;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\private_message\Service\PrivateMessageUninstallerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for the Private Message module.
 */
final class PrivateMessageCommands extends DrushCommands {

  use AutowireTrait;
  use StringTranslationTrait;

  public function __construct(
    protected readonly PrivateMessageUninstallerInterface $privateMessageUninstaller,
  ) {
    parent::__construct();
  }

  /**
   * Prepares the Private Message module for uninstallation.
   */
  #[CLI\Command(name: 'private_message:prepare_uninstall', aliases: ['pu'])]
  public function prepareUninstall(): void {
    if ($this->io()->confirm($this->t('Proceeding will delete all private messages and private message threads in your system. They cannot be recovered. Do you wish to proceed?')->render())) {
      $this->privateMessageUninstaller->initiateBatch();
      drush_backend_batch_process();

      $output = $this->output();
      $output->writeln($this->t('All private message content deleted.')->render());
    }
  }

}
