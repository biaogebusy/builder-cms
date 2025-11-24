<?php

declare(strict_types=1);

namespace Drupal\private_message\Service;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Handles uninstallation of Private Message entities with batch processing.
 */
class PrivateMessageUninstaller implements PrivateMessageUninstallerInterface {

  use StringTranslationTrait;

  /**
   * Initiates the batch process for uninstalling messages.
   */
  public function initiateBatch(): void {
    $batchBuilder = new BatchBuilder();

    $batchBuilder->setTitle($this->t('Deleting private message data'))
      ->setInitMessage($this->t('Starting the uninstallation of private data.'))
      ->setProgressMessage($this->t('Deleting private message threads...'))
      ->setErrorMessage($this->t('An error occurred during deleting the private message data.'));

    $batchBuilder->addOperation([
      get_class($this),
      'batchDeleteThreads',
    ]);
    $batchBuilder->addOperation([
      get_class($this),
      'deleteMessageBans',
    ]);

    batch_set($batchBuilder->toArray());
  }

  /**
   * Batch operation callback for processing threads dynamically.
   *
   * @param array $context
   *   Batch context array.
   */
  public static function batchDeleteThreads(array &$context): void {
    if (!isset($context['sandbox']['threads'])) {
      $threads = \Drupal::service('private_message.service')->getThreadIds();
      $context['sandbox']['threads'] = $threads;
      $context['sandbox']['total'] = count($threads);
      $context['sandbox']['processed'] = 0;
      $context['finished'] = 0;
    }

    $threadId = array_shift($context['sandbox']['threads']);
    if ($threadId) {
      self::deleteThread((int) $threadId);

      $context['sandbox']['processed']++;
      $context['message'] = t('Deleted thread @processed of @total.', [
        '@processed' => $context['sandbox']['processed'],
        '@total' => $context['sandbox']['total'],
      ]);
    }

    if (empty($context['sandbox']['threads'])) {
      $context['finished'] = 1;
    }
  }

  /**
   * Deletes message bans.
   *
   * @param array $context
   *   Batch context array.
   */
  public static function deleteMessageBans(array &$context): void {
    $storage = \Drupal::entityTypeManager()->getStorage('private_message_ban');

    if (!isset($context['sandbox']['bans'])) {
      $context['sandbox']['bans'] = $storage->getQuery()
        ->accessCheck(FALSE)
        ->execute();
      $context['sandbox']['total'] = count($context['sandbox']['bans']);
      $context['sandbox']['processed'] = 0;
      $context['finished'] = 0;
    }

    $banIds = array_splice($context['sandbox']['bans'], 0, 25);
    if (!empty($banIds)) {
      $storage->delete($storage->loadMultiple($banIds));
      $context['sandbox']['processed'] += count($banIds);
      $context['finished'] = (int) empty($context['sandbox']['bans']);

      $context['message'] = t('Removed @bans message bans out of @total.', [
        '@bans' => $context['sandbox']['processed'],
        '@total' => $context['sandbox']['total'],
      ]);
    }

    if (empty($context['sandbox']['bans'])) {
      $context['finished'] = 1;
    }
  }

  /**
   * Deletes thread and messages.
   *
   * @param int $threadId
   *   Thread id.
   */
  public static function deleteThread(int $threadId): void {
    /** @var \Drupal\private_message\Entity\PrivateMessageThreadInterface|null $thread */
    $thread = \Drupal::entityTypeManager()
      ->getStorage('private_message_thread')
      ->load($threadId);
    if (!$thread) {
      return;
    }

    foreach ($thread->getMessages() as $message) {
      $message->delete();
    }

    $thread->delete();
  }

}
