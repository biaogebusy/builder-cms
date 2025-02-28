<?php

namespace Drupal\private_message\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\private_message\Service\PrivateMessageService;
use Drush\Commands\DrushCommands;

/**
 * Creates Drush commands for the Private Message module.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class PrivateMessageCommands extends DrushCommands {

  use StringTranslationTrait;

  /**
   * The private message service.
   *
   * @var \Drupal\private_message\Service\PrivateMessageService
   */
  protected $privateMessageService;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a  object.
   *
   * @param \Drupal\private_message\Service\PrivateMessageService $privateMessageService
   *   The private message service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    PrivateMessageService $privateMessageService,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    parent::__construct();

    $this->privateMessageService = $privateMessageService;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Prepares the Private Message module for uninstallation.
   *
   * @command private_message:prepare_uninstall
   *
   * @aliases pu
   */
  public function prepareUninstall() {
    if ($this->io()->confirm($this->t('Proceeding will delete all private messages and private message threads in your system. They cannot be recovered. Do you wish to proceed?')->render())) {
      // Get the IDs of all threads in the system.
      $thread_ids = $this->privateMessageService->getThreadIds();
      // Load the private message thread storage.
      $thread_storage = $this->entityTypeManager->getStorage('private_message_thread');
      $message_storage = $this->entityTypeManager->getStorage('private_message');

      $output = $this->output();
      // Note that each thread ID is looped through, as there may be a massive
      // number of threads in the system, meaning that loading them all could be
      // a crazy memory hog and crash.
      foreach ($thread_ids as $thread_id) {
        // Load the thread.
        $thread = $thread_storage->load($thread_id);
        $message_storage->delete($thread->getMessages());
        // Delete the thread.
        $thread_storage->delete([$thread]);
        // Notify the user.
        $output->writeln($this->t('Deleted thread: @id', ['@id' => $thread_id])->render());
      }
      // Inform the user the process is finished.
      $output->writeln($this->t('All private message content deleted.')->render());
    }
  }

}
