<?php

declare(strict_types=1);

namespace Drupal\private_message\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\SettingsCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\private_message\Ajax\PrivateMessageInboxInsertThreadsCommand;
use Drupal\private_message\Ajax\PrivateMessageInboxUpdateCommand;
use Drupal\private_message\Ajax\PrivateMessageInsertNewMessagesCommand;
use Drupal\private_message\Ajax\PrivateMessageInsertPreviousMessagesCommand;
use Drupal\private_message\Ajax\PrivateMessageInsertThreadCommand;
use Drupal\private_message\Ajax\PrivateMessageUpdateUnreadItemsCountCommand;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller to handle Ajax requests.
 */
class AjaxController extends ControllerBase implements AjaxControllerInterface {

  public function __construct(
    protected readonly RendererInterface $renderer,
    protected readonly RequestStack $requestStack,
    protected readonly PrivateMessageServiceInterface $privateMessageService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function ajaxCallback(string $op): AjaxResponse {
    return match($op) {
      'get_new_messages' => $this->getNewPrivateMessages(),
      'get_old_messages' => $this->getOldPrivateMessages(),
      'get_old_inbox_threads' => $this->getOldInboxThreads(),
      'get_new_inbox_threads' => $this->getNewInboxThreads(),
      'get_new_unread_thread_count' => $this->getNewUnreadThreadCount(),
      'get_new_unread_message_count' => $this->getNewUnreadMessageCount(),
      'load_thread' => $this->loadThread(),
    };
  }

  /**
   * Creates an Ajax Command containing new private message.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response to which any commands should be attached.
   */
  protected function getNewPrivateMessages(): AjaxResponse {
    $response = new AjaxResponse();
    $thread_id = $this->requestStack->getCurrentRequest()->get('threadid');
    $message_id = $this->requestStack->getCurrentRequest()->get('messageid');

    if (is_numeric($thread_id) && is_numeric($message_id)) {
      $thread = $this->entityTypeManager()->getStorage('private_message_thread')
        ->load($thread_id);
      if ($thread instanceof PrivateMessageThreadInterface) {
        $new_messages = $this->privateMessageService->getNewMessages($thread_id, $message_id);
        $this->privateMessageService->updateThreadAccessTime($thread);
        $count = count($new_messages);
        if ($count) {
          $messages = [];
          $view_builder = $this->entityTypeManager()->getViewBuilder('private_message');
          foreach ($new_messages as $message) {
            if ($message->access('view', $this->currentUser())) {
              $message_view = $view_builder->view($message);
              $message_view['#prefix'] = '<div class="private-message-wrapper field__item">';
              $message_view['#suffix'] = '</div>';
              $messages[] = $message_view;
            }
          }

          // Ensure the browser knows the thread ID at all times.
          $messages['#attached']['drupalSettings']['privateMessageThread']['threadId'] = (int) $thread->id();
        }

        $response->addCommand(new PrivateMessageInsertNewMessagesCommand((string) $this->renderer->renderRoot($messages), $count));
      }
    }

    return $response;
  }

  /**
   * Create an Ajax Command containing old private messages.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response to which any commands should be attached.
   */
  protected function getOldPrivateMessages(): AjaxResponse {
    $response = new AjaxResponse();

    $current_request = $this->requestStack->getCurrentRequest();
    $thread_id = $current_request->get('threadid');
    $message_id = $current_request->get('messageid');
    if (is_numeric($thread_id) && is_numeric($message_id)) {
      $message_info = $this->privateMessageService->getPreviousMessages($thread_id, $message_id);

      if (count($message_info['messages'])) {
        $messages = [];
        $view_builder = $this->entityTypeManager()->getViewBuilder('private_message');
        $has_next = $message_info['next_exists'];
        foreach ($message_info['messages'] as $message) {
          if ($message->access('view', $this->currentUser())) {
            $message_view = $view_builder->view($message);
            $message_view['#prefix'] = '<div class="private-message-wrapper field__item">';
            $message_view['#suffix'] = '</div>';
            $messages[] = $message_view;
          }
        }

        $response->addCommand(new PrivateMessageInsertPreviousMessagesCommand((string) $this->renderer->renderRoot($messages), count($message_info['messages']), $has_next));
      }
      else {
        $response->addCommand(new PrivateMessageInsertPreviousMessagesCommand('', 0, FALSE));
      }
    }

    return $response;
  }

  /**
   * Creates and Ajax Command containing old threads for the inbox.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response to which any commands should be attached.
   */
  protected function getOldInboxThreads(): AjaxResponse {
    $response = new AjaxResponse();

    $timestamp = $this->requestStack->getCurrentRequest()->get('timestamp');
    $thread_count = (int) $this->requestStack->getCurrentRequest()->get('count');
    if (is_numeric($timestamp)) {
      $thread_info = $this->privateMessageService->getThreadsForUser($thread_count, intval($timestamp));
      $has_next = FALSE;
      if (count($thread_info['threads'])) {
        $view_builder = $this->entityTypeManager()->getViewBuilder('private_message_thread');
        $threads = [];
        foreach ($thread_info['threads'] as $thread) {
          if ($thread->access('view', $this->currentUser())) {
            $has_next = $thread_info['next_exists'];
            $threads[] = $view_builder->view($thread, 'inbox');
          }
        }
        $response->addCommand(new PrivateMessageInboxInsertThreadsCommand($this->renderer->renderRoot($threads), $has_next));
      }
      else {
        $response->addCommand(new PrivateMessageInboxInsertThreadsCommand('', FALSE));
      }
    }

    return $response;
  }

  /**
   * Creates an Ajax Command with new threads for the private message inbox.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response to which any commands should be attached.
   */
  protected function getNewInboxThreads(): AjaxResponse {
    $response = new AjaxResponse();

    $info = $this->requestStack->getCurrentRequest()->get('ids');

    // Check to see if any thread IDs were POSTed.
    if (is_array($info) && count($info)) {
      // Get new inbox information based on the posted IDs.
      $inbox_threads = $this->privateMessageService->getUpdatedInboxThreads($info);
    }
    else {
      // No IDs were posted, so the maximum possible number of threads to be
      // returned is retrieved from the block settings.
      $thread_count = $this->config('block.block.privatemessageinbox')
        ->get('settings.thread_count');
      $inbox_threads = $this->privateMessageService->getUpdatedInboxThreads([], $thread_count);
    }

    // Only need to do something if any thread IDS were found.
    if (count($inbox_threads['thread_ids'])) {
      $view_builder = $this->entityTypeManager()->getViewBuilder('private_message_thread');

      // Render any new threads as HTML to be sent to the browser.
      $rendered_threads = [];
      foreach (array_keys($inbox_threads['new_threads']) as $thread_id) {
        if ($inbox_threads['new_threads'][$thread_id]->access('view', $this->currentUser())) {
          $renderable = $view_builder->view($inbox_threads['new_threads'][$thread_id], 'inbox');
          $rendered_threads[$thread_id] = $this->renderer->renderRoot($renderable);
        }
      }

      // Add the command that will tell the inbox which thread items to update.
      $response->addCommand(new PrivateMessageInboxUpdateCommand($inbox_threads['thread_ids'], $rendered_threads));
    }

    return $response;
  }

  /**
   * Create Ajax Command returning the number of unread private message threads.
   *
   * Only messages created since the current user last visited the private
   * message page are shown.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response to which any commands should be attached.
   */
  protected function getNewUnreadThreadCount(): AjaxResponse {
    $response = new AjaxResponse();
    $unread_thread_count = $this->privateMessageService->getUnreadThreadCount();
    $response->addCommand(new PrivateMessageUpdateUnreadItemsCountCommand($unread_thread_count));
    return $response;
  }

  /**
   * Create Ajax Command returning the number of unread private messages.
   *
   * Only messages created since the current user last visited the private
   * message page are shown.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response to which any commands should be attached.
   */
  protected function getNewUnreadMessageCount(): AjaxResponse {
    $response = new AjaxResponse();
    $unread_message_count = $this->privateMessageService->getUnreadMessageCount();
    $response->addCommand(new PrivateMessageUpdateUnreadItemsCountCommand($unread_message_count));
    return $response;
  }

  /**
   * Load a private message thread to be dynamically inserted into the page.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response to which any commands should be attached.
   */
  protected function loadThread(): AjaxResponse {
    $response = new AjaxResponse();

    $thread_id = $this->requestStack->getCurrentRequest()->get('id');
    if ($thread_id) {
      $thread = $this->entityTypeManager()->getStorage('private_message_thread')->load($thread_id);

      if ($thread && $thread->access('view', $this->currentUser())) {
        $this->privateMessageService->updateLastCheckTime();

        $view_builder = $this->entityTypeManager()->getViewBuilder('private_message_thread');
        $renderable = $view_builder->view($thread);
        $rendered_thread = (string) $this->renderer->renderRoot($renderable);

        $response->addCommand(new SettingsCommand($renderable['#attached']['drupalSettings'], TRUE));
        $response->addCommand(new PrivateMessageInsertThreadCommand($rendered_thread));
        $unread_thread_count = $this->privateMessageService->getUnreadThreadCount();
        $response->addCommand(new PrivateMessageUpdateUnreadItemsCountCommand($unread_thread_count));
      }
    }

    return $response;
  }

}
