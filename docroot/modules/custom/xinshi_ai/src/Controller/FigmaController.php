<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\xinshi_ai\Exception\FigmaException;
use Drupal\xinshi_ai\Service\FigmaConnector;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Fixed connector operations, authenticated by the current Drupal OAuth user. */
final class FigmaController extends ControllerBase {

  public function __construct(private readonly FigmaConnector $figma) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('xinshi_ai.figma'));
  }

  public function handle(Request $request): JsonResponse {
    try {
      if (strlen($request->getContent()) > 16384) {
        throw new FigmaException('figma_invalid_request');
      }
      $input = json_decode($request->getContent(), TRUE);
      if (!is_array($input) || !is_string($input['operation'] ?? NULL)) {
        throw new FigmaException('figma_invalid_request');
      }
      $allowed = match ($input['operation']) {
        'status', 'disconnect' => [], 'authorize' => ['returnTo'],
        'callback' => ['state', 'code', 'denied'], 'read' => ['path'],
        default => throw new FigmaException('figma_invalid_request'),
      };
      if (array_diff(array_keys($input), ['operation', ...$allowed])) {
        throw new FigmaException('figma_invalid_request');
      }
      $data = match ($input['operation']) {
        'status' => $this->figma->status(),
        'disconnect' => $this->figma->disconnect(),
        'authorize' => $this->figma->authorize($input['returnTo'] ?? NULL),
        'callback' => $this->figma->complete(is_string($input['state'] ?? NULL) ? $input['state'] : '',
          $input['code'] ?? NULL, ($input['denied'] ?? FALSE) === TRUE),
        'read' => $this->figma->read(is_string($input['path'] ?? NULL) ? $input['path'] : ''),
      };
      $response = new JsonResponse($data, 200, ['Cache-Control' => 'private, no-store']);
      $response->setEncodingOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
      return $response;
    }
    catch (\Throwable $error) {
      return new JsonResponse(['code' => $error instanceof FigmaException ? $error->error : 'figma_unavailable'],
        $error instanceof FigmaException ? $error->status : 503, ['Cache-Control' => 'private, no-store']);
    }
  }

}
