<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\xinshi_ai\Exception\ImageSafetyException;

/**
 * Validates bounded raster bytes and derives MIME and extension from content.
 */
class ImageFileValidator {

  public const MAX_BYTES = 20_971_520;
  public const MAX_PIXELS = 33_554_432;

  public function fromBinary(string $binary): ImageFile {
    if ($binary === '' || strlen($binary) > self::MAX_BYTES) {
      throw new ImageSafetyException('Image is empty or exceeds 20 MiB.');
    }
    $info = @getimagesizefromstring($binary);
    $extension = match ($info[2] ?? NULL) {
      IMAGETYPE_PNG => 'png',
      IMAGETYPE_JPEG => 'jpg',
      IMAGETYPE_WEBP => 'webp',
      IMAGETYPE_GIF => 'gif',
      default => NULL,
    };
    if ($extension === NULL || $info[0] < 1 || $info[1] < 1 || $info[0] > 8192 || $info[1] > 8192
      || $info[0] * $info[1] > self::MAX_PIXELS) {
      throw new ImageSafetyException('Image must be PNG, JPEG, WebP or GIF within the dimension limits.');
    }
    // Header recognition alone accepts truncated files; require a full decode.
    if (!function_exists('imagecreatefromstring')) {
      throw new ImageSafetyException('Image validation requires GD.');
    }
    $decoded = @imagecreatefromstring($binary);
    if ($decoded === FALSE) {
      throw new ImageSafetyException('Image content could not be decoded.');
    }
    imagedestroy($decoded);
    return new ImageFile($binary, $info['mime'], 'image.' . $extension);
  }

  public function fromBase64(string $encoded): ImageFile {
    if (strlen($encoded) > 4 * (int) ceil(self::MAX_BYTES / 3)) {
      throw new ImageSafetyException('Encoded image exceeds the byte limit.');
    }
    $binary = base64_decode($encoded, TRUE);
    if ($binary === FALSE) {
      throw new ImageSafetyException('Image is not valid base64.');
    }
    return $this->fromBinary($binary);
  }

}
