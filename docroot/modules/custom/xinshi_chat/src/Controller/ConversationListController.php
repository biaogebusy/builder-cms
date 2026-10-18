<?php

declare(strict_types=1);

namespace Drupal\xinshi_chat\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\xinshi_chat\Service\ConversationList;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Preserves the first-party conversation list and pager response. */
final class ConversationListController extends ControllerBase {

  public function __construct(private readonly ConversationList $reader) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('xinshi_chat.conversations'));
  }

  public function page(Request $request): JsonResponse {
    return new JsonResponse($this->reader->page($request->query->get('page', '0'),
      $request->query->get('keyword', ''), $request->query->get('chat_type', '')),
      200, ['Cache-Control' => 'private, no-store']);
  }

  public function sessions(Request $request): JsonResponse {
    return new JsonResponse($this->reader->sessions($request->query->get('page', '0'),
      $request->query->get('keyword', '')), 200, ['Cache-Control' => 'private, no-store']);
  }

}
