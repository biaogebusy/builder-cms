<?php

namespace Drupal\content_lock\Element;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\Element\RenderElementBase;

/**
 * Provides a content_lock_messages element.
 *
 * This render element is used to add messages only during form rendering and
 * not when a form is built on form submission.
 */
#[RenderElement('content_lock_messages')]
class ContentLockMessages extends RenderElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#pre_render' => [
        [static::class, 'preRenderMessages'],
      ],
      '#message_list' => [],
    ];
  }

  /**
   * Pre-render callback: Adds messages to the messenger service.
   *
   * @param array $element
   *   An array containing a #message_list property.
   *
   * @return array
   *   A render array. This render element outputs messages to the messenger
   *   service.
   */
  public static function preRenderMessages(array $element) {
    $messenger = static::messengerService();
    foreach ($element['#message_list'] as $type => $messages) {
      assert(in_array($type, [MessengerInterface::TYPE_STATUS, MessengerInterface::TYPE_WARNING, MessengerInterface::TYPE_ERROR], TRUE), 'Message type must match a \Drupal\Core\Messenger\MessengerInterface::TYPE_ constant.');
      foreach ($messages as $message) {
        $messenger->addMessage($message, $type);
      }
    }

    return [
      // Any render that uses this element is uncacheable. Note that adding a
      // message to makes a page uncacheable using the 'page_cache_kill_switch'
      // service.
      '#cache' => ['max-age' => 0],
      // Shortcut rendering as there is nothing to do.
      '#printed' => TRUE,
    ];
  }

  /**
   * Wraps the messenger service.
   */
  protected static function messengerService(): MessengerInterface {
    return \Drupal::messenger();
  }

}
