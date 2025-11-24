<?php

/**
 * @file
 * Contains preprocess functions for the private message module.
 */

declare(strict_types=1);

use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Implements hook_preprocess_private_message_notification_block().
 */
function template_preprocess_private_message_notification_block(array &$vars): void {
  $vars['notification_image_path'] = base_path() . \Drupal::service('extension.list.module')->getPath('private_message') . '/images/private-message-notification-icon.png';

  switch ($vars['count_method']) {
    default:
    case 'threads':
      $link_title = $vars['new_items_count'] ? \Drupal::service('string_translation')->formatPlural($vars['new_items_count'], 'You have 1 unread private message', 'You have @count unread private messages') : t('You have no unread private messages');
      break;

    case 'messages':
      $link_title = $vars['new_items_count'] ? \Drupal::service('string_translation')->formatPlural($vars['new_items_count'], 'You have 1 unread thread', 'You have @count unread threads') : t('You have no unread threads');
      break;
  }

  $url = Url::fromRoute('private_message.private_message_page', [], [
    'attributes' => ['title' => $link_title, 'class' => ['private-message-page-link']],
  ]);
  $count = $vars['new_items_count'] > 99 ? '99+' : $vars['new_items_count'];
  $vars['private_message_page_link'] = Link::fromTextAndUrl($count, $url)->toString();
  $vars['unread_notification_class'] = $vars['new_items_count'] ? 'unread-threads' : '';
}
