<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Controller;

use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\Core\Controller\ControllerBase;
use Drupal\xinshi_ai\Exception\FigmaException;
use Drupal\xinshi_ai\Service\MediaUploadServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Accepts a base64-encoded raster asset from the Node chat-server and
 * saves it to the Drupal media library, returning the public file URL.
 *
 * Called only by the authenticated chat-server on behalf of the current
 * OAuth user; no Figma credentials or URLs cross this boundary.
 */
final class FigmaAssetController extends ControllerBase {

  private const ALLOWED_TYPES = [
    'image/png'  => 'png',
    'image/jpeg' => 'jpg',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
  ];

  // 8 MB raw; base64 sends ~11 MB, but we decode first before enforcing.
  private const MAX_BYTES = 8 * 1024 * 1024;

  // 12 MB JSON body limit (base64 overhead + envelope).
  private const MAX_BODY = 12 * 1024 * 1024;

  public function __construct(
    private readonly MediaUploadServiceInterface $mediaUpload,
    private readonly LoggerInterface $logger,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('xinshi_ai.media_upload'), $container->get('logger.channel.xinshi_ai'));
  }

  public function upload(Request $request): JsonResponse {
    try {
      if ($request->headers->get('Content-Length', 0) > self::MAX_BODY ||
          strlen($request->getContent()) > self::MAX_BODY) {
        throw new FigmaException('figma_too_large', 413);
      }

      $input = json_decode($request->getContent(), TRUE);
      if (!is_array($input) ||
          !is_string($input['filename'] ?? NULL) ||
          !is_string($input['mimeType'] ?? NULL) ||
          !is_string($input['data'] ?? NULL)) {
        throw new FigmaException('figma_invalid_request', 400);
      }

      $mimeType = $input['mimeType'];
      if (!isset(self::ALLOWED_TYPES[$mimeType])) {
        throw new FigmaException('figma_invalid_request', 400);
      }

      $binary = base64_decode($input['data'], TRUE);
      if ($binary === FALSE || strlen($binary) > self::MAX_BYTES) {
        throw new FigmaException('figma_too_large', 413);
      }
      if ($binary === '') {
        throw new FigmaException('figma_invalid_request', 400);
      }

      // Sanitise the filename: basename only, force the correct extension.
      $ext = self::ALLOWED_TYPES[$mimeType];
      $filename = preg_replace('/[^a-zA-Z0-9._-]/', '-', basename((string) $input['filename']));
      if ($filename === '' || $filename === '.') {
        $filename = 'figma-asset.' . $ext;
      }

      $uid  = (int) $this->currentUser()->id();
      $image = new ImageFile($binary, $mimeType, $filename);
      $media = $this->mediaUpload->fromImageFile($image, $uid, 'Figma asset');

      /** @var \Drupal\file\FileInterface $file */
      $file = $media->get('field_media_image')->entity;
      $url  = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());

      return new JsonResponse(
        ['url' => $url],
        200,
        ['Cache-Control' => 'private, no-store'],
      );
    }
    catch (\Throwable $error) {
      if (!$error instanceof FigmaException) {
        $this->logger->error('Figma media upload failed: @class: @message', [
          '@class' => get_class($error),
          '@message' => $error->getMessage(),
        ]);
      }
      $status = $error instanceof FigmaException ? $error->status : 503;
      $code   = $error instanceof FigmaException ? $error->error  : 'figma_asset_unavailable';
      return new JsonResponse(
        ['code' => $code],
        $status,
        ['Cache-Control' => 'private, no-store'],
      );
    }
  }

}
