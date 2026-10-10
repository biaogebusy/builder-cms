<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\xinshi_ai\Service\SessionContentWriter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/** Keeps content conflicts distinct from validation and authorization failures. */
final class SessionContentController extends ControllerBase {

  public function __construct(private readonly SessionContentWriter $writer) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('xinshi_ai.session_content_writer'));
  }

  public function update(string $chat_uuid, string $uuid, Request $request): JsonResponse {
    $body = $request->getContent();
    if (strlen($body) > 8 * 1024 * 1024) {
      throw new BadRequestHttpException('Session update is too large.');
    }
    $input = json_decode($body, TRUE);
    if (!is_array($input) || array_diff(array_keys($input), ['expectedHash', 'content']) ||
      !is_string($input['expectedHash'] ?? NULL) || !is_string($input['content'] ?? NULL)) {
      throw new BadRequestHttpException('Expected content and its current hash.');
    }
    $result = $this->writer->update($chat_uuid, $uuid, $input['expectedHash'], $input['content']);
    return new JsonResponse($result, $result['updated'] ? 200 : 409,
      ['Cache-Control' => 'private, no-store']);
  }

}
