<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\xinshi_ai\Service\ConversationWriter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/** Authenticated conversation mutations share the same lock as message appends. */
final class ConversationController extends ControllerBase {

  public function __construct(private readonly ConversationWriter $writer) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('xinshi_ai.conversation_writer'));
  }

  public function update(string $uuid, Request $request): JsonResponse {
    $input = json_decode($request->getContent(), TRUE);
    if (strlen($request->getContent()) > 32768 || !is_array($input) ||
      array_diff(array_keys($input), ['sessions', 'attributes']) ||
      !is_array($input['sessions'] ?? NULL) || !is_array($input['attributes'] ?? NULL)) {
      throw new BadRequestHttpException('Expected sessions and attributes.');
    }
    return new JsonResponse($this->writer->update($uuid, $input['sessions'], $input['attributes']),
      200, ['Cache-Control' => 'private, no-store']);
  }

}
