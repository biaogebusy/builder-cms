<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Drupal\xinshi_ai\Exception\InputImageAccessException;

/**
 * Authorizes source images as the submitter, including inside queue workers.
 */
class InputImageAccess {

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly EntityRepositoryInterface $repository,
    private readonly ModuleHandlerInterface $modules,
    private readonly AccountSwitcherInterface $accounts,
    private readonly StreamWrapperManagerInterface $wrappers,
    private readonly ImageFileValidator $validator,
  ) {}

  public function forSubmission(mixed $uuid, AccountInterface $owner): MediaInterface {
    if (!is_string($uuid) || !Uuid::isValid($uuid)) {
      throw new InputImageAccessException();
    }
    $media = $this->repository->loadEntityByUuid('media', $uuid);
    if (!$media instanceof MediaInterface) {
      throw new InputImageAccessException();
    }
    $this->authorizedFile($media, $this->activeOwner($owner->id()));
    return $media;
  }

  /**
   * Reloads all references so queued work cannot retain revoked authorization.
   */
  public function forJob(NodeInterface $job): ImageFile {
    $owner = $this->activeOwner($job->getOwnerId());
    if (!$job->hasField('field_input_image') || !$job->get('field_input_image')->access('view', $owner)) {
      throw new InputImageAccessException();
    }
    $mid = $job->get('field_input_image')->target_id;
    if (!$mid) {
      throw new InputImageAccessException();
    }
    $storage = $this->entities->getStorage('media');
    $storage->resetCache([$mid]);
    $media = $storage->load($mid);
    if (!$media instanceof MediaInterface) {
      throw new InputImageAccessException();
    }
    $file = $this->authorizedFile($media, $owner);
    $path = $this->localPath($file);
    $handle = @fopen($path, 'rb');
    if ($handle === FALSE) {
      throw new InputImageAccessException();
    }
    try {
      $binary = stream_get_contents($handle, ImageFileValidator::MAX_BYTES + 1);
      if ($binary === FALSE) {
        throw new InputImageAccessException();
      }
      return $this->validator->fromBinary($binary);
    }
    finally {
      fclose($handle);
    }
  }

  private function activeOwner(int|string|null $uid): UserInterface {
    if (!$uid) {
      throw new InputImageAccessException();
    }
    $storage = $this->entities->getStorage('user');
    $storage->resetCache([$uid]);
    $owner = $storage->load($uid);
    if (!$owner instanceof UserInterface || !$owner->isActive() || !$owner->hasPermission('create xinshi_ai image job')) {
      throw new InputImageAccessException();
    }
    return $owner;
  }

  private function authorizedFile(MediaInterface $media, AccountInterface $owner): FileInterface {
    // Private-download hooks use current_user rather than an explicit argument.
    $this->accounts->switchTo($owner);
    try {
      if ($media->bundle() !== 'image' || !$media->access('view', $owner)
        || !$media->hasField('field_media_image') || !$media->get('field_media_image')->access('view', $owner)) {
        throw new InputImageAccessException();
      }
      $fid = $media->get('field_media_image')->target_id;
      if (!$fid) {
        throw new InputImageAccessException();
      }
      $storage = $this->entities->getStorage('file');
      $storage->resetCache([$fid]);
      $file = $storage->load($fid);
      if (!$file instanceof FileInterface || !$file->access('download', $owner)) {
        throw new InputImageAccessException();
      }
      $this->localPath($file);
      if (str_starts_with($file->getFileUri(), 'private://')) {
        $headers = $this->modules->invokeAll('file_download', [$file->getFileUri()]);
        if (!$headers || in_array(-1, $headers, TRUE)) {
          throw new InputImageAccessException();
        }
      }
      return $file;
    }
    finally {
      $this->accounts->switchBack();
    }
  }

  private function localPath(FileInterface $file): string {
    $uri = $file->getFileUri();
    if (!preg_match('~\A(?:public|private)://~', $uri)) {
      throw new InputImageAccessException();
    }
    $wrapper = $this->wrappers->getViaUri($uri);
    if (!$wrapper instanceof LocalStream) {
      throw new InputImageAccessException();
    }
    $root = realpath($wrapper->getDirectoryPath());
    $path = $wrapper->realpath();
    if (!$root || !$path || !str_starts_with($path, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
      || !is_file($path) || !is_readable($path) || filesize($path) > ImageFileValidator::MAX_BYTES) {
      throw new InputImageAccessException();
    }
    return $path;
  }

}
