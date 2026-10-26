<?php

declare(strict_types=1);

namespace Drupal\xinshi_shooting_plan\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\xinshi_shooting_plan\Service\ShootingSharing;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Fixed sharing endpoints; anonymous reads never expose the underlying node or job resource. */
final class ShootingSharingController extends ControllerBase {

  private const HEADERS = ['Cache-Control' => 'private, no-store',
    'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow',
    'X-Content-Type-Options' => 'nosniff'];

  public function __construct(
    private readonly ShootingSharing $sharing,
    private readonly FileSystemInterface $files,
    private readonly EntityTypeManagerInterface $entities,
  ) {}

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('xinshi_shooting_plan.sharing'), $container->get('file_system'),
      $container->get('entity_type.manager'));
  }

  /** Returns or updates the author's sharing choices. */
  public function settings(string $plan_uuid, Request $request): Response {
    return $this->respond(fn() => $request->isMethod('GET') ? $this->sharing->settings($plan_uuid) :
      $this->sharing->configure($plan_uuid, $this->input($request)));
  }

  /** Searches the minimal account-picker projection under the author's plan authority. */
  public function users(string $plan_uuid, Request $request): Response {
    return $this->respond(fn() => ['users' => $this->sharing->users($plan_uuid, $request->query->getString('q'))]);
  }

  /** Returns the current publicly shared working copy. */
  public function read(string $token): Response {
    return $this->respond(fn() => $this->sharing->read($token));
  }

  /** Accepts only the working-copy write authorized by the current sharing grant. */
  public function update(string $token, Request $request): Response {
    return $this->respond(fn() => $this->sharing->update($token, $this->input($request)));
  }

  /** Streams local image bytes after rechecking both the token and source permissions. */
  public function image(string $token, string $shot_id, Request $request): Response {
    return $this->respond(function () use ($token, $shot_id, $request) {
      $file = $this->sharing->image($token, $shot_id);
      $size = $request->query->all()['size'] ?? 'original';
      if (!in_array($size, ['original', 'thumbnail'], TRUE)) {
        throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException();
      }
      $path = $this->files->realpath($file->getFileUri());
      if (!$path || !is_file($path)) {
        throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
      }
      if ($size === 'thumbnail') {
        $style = $this->entities->getStorage('image_style')->load('large');
        $uri = $style?->buildUri($file->getFileUri());
        if (!$uri || (!is_file($uri) && !$style->createDerivative($file->getFileUri(), $uri))) {
          throw new \Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException();
        }
        $path = $this->files->realpath($uri);
      }
      $response = new BinaryFileResponse($path, 200, self::HEADERS, FALSE);
      $response->headers->set('Content-Type', $response->getFile()->getMimeType() ?? 'application/octet-stream');
      return $response;
    });
  }

  private function input(Request $request): array {
    if (strlen($request->getContent()) > 1024 * 1024) {
      throw new \Symfony\Component\HttpKernel\Exception\HttpException(413);
    }
    $input = json_decode($request->getContent(), TRUE);
    if (!is_array($input) || array_is_list($input)) {
      throw new \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException();
    }
    return $input;
  }

  private function respond(callable $operation): Response {
    try {
      $result = $operation();
      return $result instanceof Response ? $result : new JsonResponse($result, 200, self::HEADERS);
    }
    catch (\Drupal\xinshi_ai\Exception\InputImageAccessException) {
      return new JsonResponse(['message' => 'shooting_reference_unavailable'], 403, $headers);
    }
    catch (HttpExceptionInterface $error) {
      return new JsonResponse(['message' => $error->getMessage() ?: 'shooting_share_unavailable'],
        $error->getStatusCode(), self::HEADERS);
    }
    catch (\Throwable) {
      return new JsonResponse(['message' => 'shooting_share_failed'], 503, self::HEADERS);
    }
  }

}
