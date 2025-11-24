<?php

declare(strict_types=1);

namespace Drupal\private_message_notify\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\message_notify\MessageNotifier;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Drupal\private_message\Traits\PrivateMessageSettingsTrait;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * A service class for sending notifications of private messages.
 */
class PrivateMessageNotifier implements PrivateMessageNotifierInterface {

  use PrivateMessageSettingsTrait;

  public function __construct(
    protected readonly PrivateMessageServiceInterface $privateMessageService,
    protected readonly AccountProxyInterface $currentUser,
    protected readonly UserDataInterface $userData,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'message_notify.sender')]
    protected readonly MessageNotifier $messageNotifier,
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function notify(PrivateMessageInterface $message, PrivateMessageThreadInterface $thread): void {
    $members = $this->getRecipients($message, $thread);

    foreach ($members as $member) {
      // Skip the current user and any member without a valid email.
      if ($member->id() == $this->currentUser->id()) {
        continue;
      }

      // Assuming getEmail() method exists.
      $email = $member->getEmail();
      if (empty($email)) {
        // Log a warning if the email is missing, then skip this member.
        $this->loggerFactory->get('private_message_notify')
          ->warning('Notification not sent to user ID @uid due to missing email.', [
            '@uid' => $member->id(),
          ]);
        continue;
      }

      // Check if the notification should be sent.
      if (!$this->shouldSend($member, $message, $thread)) {
        continue;
      }

      // Create and send the notification.
      $notification = $this->entityTypeManager
        ->getStorage('message')
        ->create([
          'template' => 'private_message_notification',
          'uid' => $member->id(),
          'field_message_private_message' => $message,
          'field_message_pm_thread' => $thread,
        ]);
      $notification->setLanguage($member->getPreferredLangcode());
      $notification->save();

      $this->messageNotifier->send($notification);
    }
  }

  /**
   * Determines if the message should be sent.
   *
   * Checks individual user preferences as well as system defaults.
   *
   * @param \Drupal\Core\Session\AccountInterface $recipient
   *   The potential recipient.
   * @param \Drupal\private_message\Entity\PrivateMessageInterface $message
   *   The private message for which a notification is being sent.
   * @param \Drupal\private_message\Entity\PrivateMessageThreadInterface $thread
   *   The private message thread.
   *
   * @return bool
   *   A boolean indicating whether the message should be sent.
   */
  private function shouldSend(AccountInterface $recipient, PrivateMessageInterface $message, PrivateMessageThreadInterface $thread): bool {
    // Don't notify the user by default.
    $notify = FALSE;

    // Check if notifications have been enabled.
    if ($this->getPrivateMessageSettings()->get('enable_notifications')) {

      // Eligibility to receive notifications will be checked.
      $eligible_to_receive = FALSE;

      // Get the user default.
      $user_default = $this->userData->get('private_message', $recipient->id(), 'receive_notification');
      // Check if the user default is to notify.
      if ($user_default) {
        $eligible_to_receive = TRUE;
      }
      // Check if the user has not made any selection, and the system default is
      // to send:
      elseif (is_null($user_default) && $this->getPrivateMessageSettings()->get('notify_by_default')) {
        $eligible_to_receive = TRUE;
      }

      // If the user is eligible to receive notification, user and system
      // settings are  used to determine whether or not the notification should
      // be sent.
      if ($eligible_to_receive) {

        // Determine whether a user should always be notified of every message,
        // or if they should only be notified when they aren't viewing a thread.
        $notify_when_using = $this->userData->get('private_message', $recipient->id(), 'notify_when_using');
        // Check if the user has not yet set a value.
        if (is_null($notify_when_using)) {
          // The user has not yet set a value, so use the system default.
          $notify_when_using = $this->getPrivateMessageSettings()->get('notify_when_using');
        }

        // Get the number of seconds a user has set in their profile, after
        // which they should be considered 'away' from the thread.
        $away_time = $this->userData->get('private_message', $recipient->id(), 'number_of_seconds_considered_away');
        // Check if the user has not yet set a value.
        if (is_null($away_time)) {
          // The user has not yet set a value, so use the system default.
          $away_time = $this->getPrivateMessageSettings()->get('number_of_seconds_considered_away');
        }

        // Check if users should always be notified.
        if ($notify_when_using == 'yes') {
          $notify = TRUE;
        }
        // Check if users have been away for long enough to be considered away:
        elseif (($message->getCreatedTime() - $thread->getLastAccessTimestamp($recipient)) > $away_time) {
          $notify = TRUE;
        }
      }
    }

    return $notify;
  }

  /**
   * Returns the list of recipients as user accounts.
   *
   * @return \Drupal\Core\Session\AccountInterface[]
   *   Array of thread members user entities receiving the notification.
   */
  protected function getRecipients(PrivateMessageInterface $message, PrivateMessageThreadInterface $thread): array {
    $recipients = $thread->getMembers();
    $exclude = [];

    // Allow other modules to alter notification recipients.
    $this->moduleHandler->invokeAll('private_message_notify_exclude', [
      $message,
      $thread,
      &$exclude,
    ]);

    if (empty($exclude)) {
      return $recipients;
    }

    return array_filter(
      $recipients, static function (AccountInterface $account) use ($exclude) {
        // If this user is in the excluded list, filter them from the recipients
        // list, so they do not receive the notification.
        return !in_array($account->id(), $exclude);
      }
    );
  }

  /**
   * The users to receive notifications.
   *
   * @return \Drupal\Core\Session\AccountInterface[]
   *   An array of Account objects of the thread members who are to receive
   *   the notification.
   *
   * @deprecated in private_message:4.0.0 and is removed from
   *   private_message:5.0.0. Instead, use self ::getRecipients()
   *
   * @see https://www.drupal.org/node/3490530
   */
  public function getNotificationRecipients(PrivateMessageInterface $message, PrivateMessageThreadInterface $thread) {
    @trigger_error(__METHOD__ . '() is deprecated in private_message:4.0.0 and is removed from private_message:5.0.0. Instead, use self ::getRecipients(). See https://www.drupal.org/node/3490530', E_USER_DEPRECATED);
    return $this->getRecipients($message, $thread);
  }

}
