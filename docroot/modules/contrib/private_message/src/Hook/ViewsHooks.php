<?php

declare(strict_types=1);

namespace Drupal\private_message\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations used to provide the views integration.
 */
class ViewsHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_views_data().
   */
  #[Hook('views_data')]
  public function viewsData(): array {
    $data['private_message_threads']['has_history'] = [
      'title' => $this->t('Clean history'),
      'group' => $this->t('Private Message Thread'),
      'filter' => [
        'title' => $this->t('Thread has history'),
        'help' => $this->t('Filter threads by existing history.'),
        'field' => 'title',
        'id' => 'private_message_thread_has_history',
      ],
    ];
    $data['private_message_threads']['is_unread'] = [
      'title' => $this->t('Thread is unread'),
      'group' => $this->t('Private Message Thread'),
      'filter' => [
        'title' => $this->t('Thread is unread'),
        'help' => $this->t('Filter threads by the fact it is unread.'),
        'field' => 'title',
        'id' => 'private_message_thread_is_unread',
      ],
    ];

    $data['private_message_thread__members']['members_filter'] = [
      'title' => $this->t('Member(s) filter'),
      'group' => $this->t('Private Message Thread'),
      'filter' => [
        'title' => $this->t('Thread members'),
        'help' => $this->t("Filter threads by it's members."),
        'id' => 'user_name',
        'field' => 'members_target_id',
      ],
    ];

    $data['private_message_threads']['has_new_message_marker'] = [
      'title' => $this->t('Has new message marker'),
      'group' => $this->t('Private Message Thread'),
      'field' => [
        'title' => $this->t('Has new message marker'),
        'help' => $this->t('Outputs new message marker.'),
        'id' => 'private_message_thread_has_new_message_marker',
      ],
    ];

    $data['private_message_threads']['new_messages_count'] = [
      'title' => $this->t('New messages count'),
      'group' => $this->t('Private Message Thread'),
      'field' => [
        'title' => $this->t('New messages count'),
        'help' => $this->t('Outputs new message count.'),
        'id' => 'private_message_thread_new_messages_count',
      ],
    ];

    $data['private_message_threads']['all_messages_number'] = [
      'title' => $this->t('All messages number'),
      'group' => $this->t('Private Message Thread'),
      'field' => [
        'title' => $this->t('All messages number'),
        'help' => $this->t('Outputs simple messages count.'),
        'id' => 'private_message_thread_all_messages_number',
      ],
    ];
    return $data;
  }

}
