<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_reference\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\xinshi_ai_reference\ChatHistoryReader;
use Drupal\xinshi_ai_reference\ChatStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Exposes authenticated, uncached chat protocol and history reads. */
final class ChatHistoryController extends ControllerBase {

  public function __construct(private readonly ChatHistoryReader $reader, private readonly ChatStorage $protocol) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('xinshi_ai_reference.history'), $container->get('xinshi_ai_reference.storage'));
  }

  public function storage(): JsonResponse {
    return new JsonResponse(['storage_version' => $this->protocol->version()]);
  }

  public function page(string $chat_uuid, Request $request): JsonResponse {
    return new JsonResponse($this->reader->page($chat_uuid, $request->query->get('cursor')));
  }

  public function session(string $chat_uuid, string $uuid): JsonResponse {
    return new JsonResponse(['session' => $this->reader->session($chat_uuid, $uuid)]);
  }

}
