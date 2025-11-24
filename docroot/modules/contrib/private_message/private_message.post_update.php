<?php

/**
 * @file
 * Contains post update functions for private_message.
 */

declare(strict_types=1);

use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Remove orphaned private messages.
 */
function private_message_post_update_remove_orphaned_messages(array &$sandbox = []): TranslatableMarkup {
  $message_storage = \Drupal::entityTypeManager()->getStorage('private_message');
  if (!$message_storage instanceof SqlEntityStorageInterface) {
    $sandbox['#finished'] = 1;
    return t("Removing orphan private messages is only possible when the 'private_message' uses the standards SQL storage. Your project uses a different storage, you'll have to provide your own update path");
  }

  if (!isset($sandbox['message_ids'])) {
    $connection = \Drupal::database();
    $query = $connection->select('private_messages', 'pm');
    $query->fields('pm', ['id']);
    $query->leftJoin('private_message_thread__private_messages', 'pmt', 'pm.id = pmt.private_messages_target_id');
    $query->isNull('pmt.entity_id');
    $sandbox['message_ids'] = $query->execute()->fetchCol();
    $sandbox['total'] = count($sandbox['message_ids']);
    $sandbox['current'] = 0;
  }

  $message_ids = array_splice($sandbox['message_ids'], 0, 25);
  $message_storage->delete($message_storage->loadMultiple($message_ids));
  $sandbox['current'] += count($message_ids);

  $sandbox['#finished'] = (int) empty($sandbox['message_ids']);
  return t('Removed @current of @total orphaned private messages.', [
    '@current' => $sandbox['current'],
    '@total' => $sandbox['total'],
  ]);
}
