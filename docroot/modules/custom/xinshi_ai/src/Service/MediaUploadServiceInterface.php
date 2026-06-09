<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\media\MediaInterface;

/**
 * 把 Provider 产出的图片二进制落成 media:image 实体。
 */
interface MediaUploadServiceInterface {

  /**
   * 从 drupal/ai 的 ImageFile(已含二进制)创建 media:image。
   *
   * @param \Drupal\ai\OperationType\GenericType\ImageFile $image
   *   Provider 返回的图片(getBinary / getMimeType / getFilename)。
   * @param int $uid
   *   归属用户。
   * @param string $alt
   *   可选 alt 文本。
   */
  public function fromImageFile(ImageFile $image, int $uid, string $alt = ''): MediaInterface;

}
