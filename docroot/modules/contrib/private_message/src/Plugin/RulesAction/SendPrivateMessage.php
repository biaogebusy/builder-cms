<?php

namespace Drupal\private_message\Plugin\RulesAction;

use Drupal\rules\Core\RulesActionBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\UserInterface;
use Drupal\private_message\Entity\PrivateMessage;

/**
 * Provides "Send private message" rules action.
 *
 * @RulesAction(
 *   id = "private_message_send_message",
 *   label = @Translation("Send private message"),
 *   category = @Translation("System"),
 *   context_definitions = {
 *     "author" = @ContextDefinition("entity:user",
 *       label = @Translation("From"),
 *       description = @Translation("The author of the message.")
 *     ),
 *     "recipient" = @ContextDefinition("entity:user",
 *       label = @Translation("To"),
 *       description = @Translation("The recipient of the message.")
 *     ),
 *     "message" = @ContextDefinition("string",
 *       label = @Translation("Message"),
 *       description = @Translation("The message.")
 *     ),
 *   }
 * )
 */
class SendPrivateMessage extends RulesActionBase implements ContainerFactoryPluginInterface {

  /**
   * The Private Message service.
   *
   * @var \Drupal\private_message\Service\PrivateMessageService
   */
  protected $privateMessageService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->privateMessageService = $container->get('private_message.service');
    return $instance;
  }

  /**
   * Send a private message.
   *
   * @param \Drupal\user\UserInterface $author
   *   The author of the message.
   * @param \Drupal\user\UserInterface $recipient
   *   The recipient of the message.
   * @param string $message
   *   The text of the message.
   */
  protected function doExecute(UserInterface $author, UserInterface $recipient, $message) {
    $members = [$author, $recipient];
    // Create a thread if one does not exist.
    $private_message_thread = $this->privateMessageService->getThreadForMembers($members);
    // Add a Message to the thread.
    $private_message = PrivateMessage::create();
    $private_message->set('owner', $author);
    $private_message->set('message', $message);
    $private_message->save();
    $private_message_thread->addMessage($private_message)->save();
  }

}
