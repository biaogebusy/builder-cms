<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\media\MediaInterface;

/**
 * 落地策略:写入 public://xinshi_ai/YYYY-MM/,创建托管 file,再建 media:image。
 *
 * 站点 media:image 的 source 字段为 field_media_image(已确认)。
 */
final class MediaUploadService implements MediaUploadServiceInterface {

  private const MEDIA_BUNDLE = 'image';
  private const SOURCE_FIELD = 'field_media_image';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileRepositoryInterface $fileRepository,
    private readonly FileSystemInterface $fileSystem,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function fromImageFile(ImageFile $image, int $uid, string $alt = ''): MediaInterface {
    $dir = 'public://xinshi_ai/' . date('Y-m');
    $this->fileSystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    $filename = $this->safeFilename($image->getFilename());
    $destination = $dir . '/' . uniqid('', TRUE) . '-' . $filename;

    $file = $this->fileRepository->writeData($image->getBinary(), $destination, FileExists::Rename);
    $file->setOwnerId($uid);
    $file->setPermanent();
    $file->save();

    $media = $this->mediaStorage()->create([
      'bundle' => self::MEDIA_BUNDLE,
      'uid' => $uid,
      'name' => $file->getFilename(),
      self::SOURCE_FIELD => [
        'target_id' => $file->id(),
        'alt' => mb_substr($alt, 0, 512),
      ],
    ]);
    $media->save();
    assert($media instanceof MediaInterface);
    return $media;
  }

  private function safeFilename(string $filename): string {
    $filename = basename($filename);
    return $filename !== '' ? $filename : 'image.png';
  }

  private function mediaStorage() {
    return $this->entityTypeManager->getStorage('media');
  }

}
