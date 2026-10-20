<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\xinshi_ai\Service\WebSearchClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** OAuth-only read operations; responses never contain the provider key. */
final class WebSearchController extends ControllerBase {

  public function __construct(private readonly WebSearchClient $client) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('xinshi_ai.web_search'));
  }

  public function read(Request $request, string $operation): JsonResponse {
    $headers = ['Cache-Control' => 'private, no-store'];
    try {
      if (strlen($request->getContent()) > 16384) {
        throw new HttpException(413, 'web_search_invalid_input');
      }
      $input = json_decode($request->getContent());
      if (!$input instanceof \stdClass) {
        throw new HttpException(400, 'web_search_invalid_input');
      }
      return new JsonResponse($this->client->read($operation, (array) $input), 200, $headers);
    }
    catch (HttpException $error) {
      return new JsonResponse(['code' => $error->getMessage()], $error->getStatusCode(), $headers);
    }
    catch (\Throwable) {
      return new JsonResponse(['code' => 'web_search_unavailable'], 503, $headers);
    }
  }

}
