<?php

declare(strict_types=1);

namespace Drupal\xinshi_media;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\FileRepositoryInterface;

/** Creates one validated media record with the authenticated uploader as its owner. */
final class MediaUpload {

  public function __construct(
    private readonly MediaCatalog $catalog, private readonly MediaTypes $types,
    private readonly MediaValidator $validator, private readonly EntityTypeManagerInterface $entities,
    private readonly AccountInterface $account, private readonly FileRepositoryInterface $files,
    private readonly FileSystemInterface $filesystem, private readonly Connection $database,
  ) {}

  public function create(string $bundle, string $filename, string $bytes): array {
    $type = $this->types->get($bundle);
    if (!$this->account->isAuthenticated() || !$this->entities->getAccessControlHandler('media')->createAccess($bundle, $this->account)) {
      throw new MediaException('permission_denied', 403);
    }
    if ($filename === '' || mb_strlen($filename) > 255 || preg_match('@[\\/\x00-\x1f\x7f]@', $filename)) {
      throw new MediaException('invalid_filename');
    }
    $media = $this->entities->getStorage('media')->create(['bundle' => $bundle,
      'uid' => $this->account->id(), 'name' => $filename, 'status' => 1]);
    if (!$media->get($type['field'])->access('edit', $this->account) || !$media->get('name')->access('edit', $this->account)) {
      throw new MediaException('permission_denied', 403);
    }
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    [$bytes, $mime] = $this->validator->validate($bytes, $extension, $type);
    $directory = $media->get($type['field'])->appendItem()->getUploadLocation();
    if (!$this->filesystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      throw new MediaException('storage_unavailable', 503);
    }
    $destination = rtrim($directory, '/') . '/' . bin2hex(random_bytes(16)) . '.' . $extension;
    $transaction = $this->database->startTransaction();
    try {
      $file = $this->files->writeData($bytes, $destination, FileExists::Error);
      $file->setOwnerId($this->account->id());
      $file->setFilename($filename);
      $file->setMimeType($mime);
      $file->setPermanent();
      $file->save();
      $value = ['target_id' => $file->id()];
      if ($type['kind'] === 'image') {
        $image = getimagesizefromstring($bytes);
        $value += ['alt' => pathinfo($filename, PATHINFO_FILENAME), 'width' => $image[0], 'height' => $image[1]];
      }
      $media->set($type['field'], $value);
      if (count($media->validate())) throw new MediaException('invalid_media', 422);
      $media->save();
      // Validation can cache an empty reference list before the media exists in this transaction.
      \Drupal::service('cache.memory')->delete('file_references:' . $file->id());
      $this->entities->getAccessControlHandler('file')->resetCache();
      $result = $this->catalog->row($media);
      unset($transaction);
      return $result;
    } catch (\Throwable $error) {
      $transaction->rollBack();
      // Only this attempt knows the randomly generated path; no prior file or reference is removed.
      if (file_exists($destination)) $this->filesystem->delete($destination);
      throw $error;
    }
  }

}
