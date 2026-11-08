<?php

declare(strict_types=1);

namespace Drupal\xinshi_chat\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\xinshi_chat\Service\PublishedPage;
use Drupal\xinshi_ai_reference\ChatSessionFormat;
use Drupal\xinshi_ai_reference\ChatSessionFormatException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** Separates authenticated publishing from the intentionally public snapshot reader. */
final class PublishedPageController extends ControllerBase {

  public function __construct(private readonly PublishedPage $pages) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('xinshi_chat.published_page'));
  }

  public function manage(string $chat_uuid, string $uuid, Request $request): Response {
    if ($request->isMethod('PUT')) {
      try {
        $body = strlen($request->getContent()) <= 256
          ? ChatSessionFormat::decode($request->getContent()) : NULL;
      }
      catch (ChatSessionFormatException) {
        $body = NULL;
      }
      if (!$body instanceof \stdClass || array_keys(get_object_vars($body)) !== ['html_hash'] ||
        !is_string($body->html_hash) || !preg_match('/\A[a-f0-9]{64}\z/', $body->html_hash)) {
        throw new UnprocessableEntityHttpException('Expected one HTML source hash.');
      }
      $result = $this->pages->publish($chat_uuid, $uuid, $body->html_hash);
    }
    elseif ($request->getContent() !== '') {
      throw new UnprocessableEntityHttpException('This operation does not accept a body.');
    }
    elseif ($request->isMethod('DELETE')) {
      $this->pages->revoke($chat_uuid, $uuid);
      return new Response('', 204, ['Cache-Control' => 'private, no-store']);
    }
    else {
      $result = $this->pages->status($chat_uuid, $uuid);
    }
    return new JsonResponse($result, 200, ['Cache-Control' => 'private, no-store']);
  }

  public function read(string $uuid): JsonResponse {
    $page = $this->pages->read($uuid);
    return new JsonResponse($page ?? ['error' => 'page_unavailable'], $page ? 200 : 404,
      ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
  }

}
