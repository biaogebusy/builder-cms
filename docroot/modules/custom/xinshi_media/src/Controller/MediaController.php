<?php

declare(strict_types=1);

namespace Drupal\xinshi_media\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\xinshi_media\MediaCatalog;
use Drupal\xinshi_media\MediaException;
use Drupal\xinshi_media\MediaTypes;
use Drupal\xinshi_media\MediaUpload;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/** Transports authorized media metadata and bounded binary uploads. */
final class MediaController extends ControllerBase {

  public function __construct(
    private readonly MediaCatalog $catalog, private readonly MediaUpload $uploads,
    private readonly MediaTypes $types,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('xinshi_media.catalog'), $container->get('xinshi_media.upload'), $container->get('xinshi_media.types'));
  }

  public function capabilities(): JsonResponse {
    return new JsonResponse($this->catalog->capabilities());
  }

  public function listing(Request $request): JsonResponse {
    return new JsonResponse($this->catalog->listing($request->query->all()));
  }

  public function read(string $media_id): JsonResponse {
    return new JsonResponse($this->catalog->row($this->catalog->load($media_id)));
  }

  public function display(string $media_id): JsonResponse {
    return new JsonResponse($this->catalog->display($media_id));
  }

  public function upload(string $bundle, Request $request): JsonResponse {
    $maximum = $this->types->get($bundle)['maxBytes'];
    if (!preg_match('/^file; filename="([^"\r\n]+)"$/D', $request->headers->get('Content-Disposition', ''), $match)) {
      throw new MediaException('invalid_filename');
    }
    if ($request->headers->get('Content-Type') !== 'application/octet-stream') throw new MediaException('invalid_content_type', 415);
    $stream = $request->getContent(TRUE);
    $bytes = stream_get_contents($stream, $maximum + 1);
    if ($bytes === FALSE) throw new MediaException('invalid_file');
    return new JsonResponse($this->uploads->create($bundle, rawurldecode($match[1]), $bytes), 201);
  }

  public function rename(string $media_id, Request $request): JsonResponse {
    if (strlen($request->getContent()) > 4096) throw new MediaException('invalid_name');
    try { $input = json_decode($request->getContent(), TRUE, 8, JSON_THROW_ON_ERROR); }
    catch (\JsonException) { throw new MediaException('invalid_name'); }
    if (!is_array($input)) throw new MediaException('invalid_name');
    return new JsonResponse($this->catalog->rename($media_id, $input));
  }

  public function remove(string $media_id): Response {
    $this->catalog->remove($media_id);
    return new Response('', 204);
  }

  public function file(string $media_id, Request $request): BinaryFileResponse {
    $media = $this->catalog->load($media_id);
    $file = $this->catalog->file($media);
    $uri = $file->getFileUri();
    if (!preg_match('@^(public|private)://@', $uri) || !is_file($uri)) throw new MediaException('media_unavailable', 404);
    $response = new BinaryFileResponse($uri);
    $response->headers->set('Content-Type', $file->getMimeType());
    $response->headers->set('Content-Security-Policy', "sandbox; default-src 'none'; style-src 'unsafe-inline'");
    $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file->getFilename(), 'download');
    $response->setPrivate();
    return $response;
  }

}
