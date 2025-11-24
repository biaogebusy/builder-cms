<?php

declare(strict_types=1);

namespace Drupal\private_message\Plugin\RulesAction;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\private_message\Entity\PrivateMessage;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Drupal\rules\Context\ContextDefinition;
use Drupal\rules\Core\Attribute\RulesAction;
use Drupal\rules\Core\RulesActionBase;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides "Send private message" rules action.
 */
#[RulesAction(
  id: 'private_message_send_message',
  label: new TranslatableMarkup('Send private message'),
  category: new TranslatableMarkup('System'),
  context_definitions: [
    'author' => new ContextDefinition(
      data_type: 'entity:user',
      label: new TranslatableMarkup('From'),
      description: new TranslatableMarkup('The author of the message.')
    ),
    'recipient' => new ContextDefinition(
      data_type: 'entity:user',
      label: new TranslatableMarkup('To'),
      description: new TranslatableMarkup('The recipient of the message.')
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Message'),
      description: new TranslatableMarkup('The message.')
    ),
  ],
)]
class SendPrivateMessage extends RulesActionBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly PrivateMessageServiceInterface $privateMessageService,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('private_message.service'),
    );
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
  protected function doExecute(UserInterface $author, UserInterface $recipient, string $message): void {
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
