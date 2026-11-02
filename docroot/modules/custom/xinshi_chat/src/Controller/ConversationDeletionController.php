<?php

declare(strict_types=1);

namespace Drupal\xinshi_chat\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\xinshi_chat\Service\ConversationDeletion;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/** Accepts a conversation deletion intent without a client-supplied message list. */
final class ConversationDeletionController extends ControllerBase {

  public function __construct(private readonly ConversationDeletion $deletion) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('xinshi_chat.conversation_deletion'));
  }

  public function delete(string $uuid): Response {
    $this->deletion->delete($uuid);
    return new Response(NULL, 204, ['Cache-Control' => 'private, no-store']);
  }

}
