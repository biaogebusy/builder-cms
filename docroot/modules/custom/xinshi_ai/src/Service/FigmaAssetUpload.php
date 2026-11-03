<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\xinshi_ai\Exception\FigmaException;

/** Commits owner-bound import receipts with the media entities they identify. */
class FigmaAssetUpload {

  private const COLLECTION = 'xinshi_ai.figma_assets';

  public function __construct(
    private readonly Connection $database,
    private readonly MediaUploadServiceInterface $upload,
    private readonly EntityTypeManagerInterface $entities,
    private readonly FileUrlGeneratorInterface $urls,
  ) {}

  /** Reuses a confirmed import without recreating a removed or changed resource. */
  public function import(ImageFile $image, string $digest, AccountInterface $account, string $importId): string {
    $uid = (int) $account->id();
    if ($uid <= 0 || !preg_match('/^[a-f0-9]{8}(-[a-f0-9]{4}){3}-[a-f0-9]{12}$/D', $importId) ||
      !preg_match('/^[a-f0-9]{64}$/D', $digest) ||
      !hash_equals($digest, hash('sha256', $image->getBinary()))) {
      throw new FigmaException('figma_invalid_request', 400);
    }
    $key = ['collection' => self::COLLECTION, 'name' => $uid . ':' . $importId . ':' . $digest];
    $transaction = $this->database->startTransaction();
    try {
      // A missing receipt is inserted in this transaction. Concurrent imports then wait on its row.
      $this->database->merge('key_value')->insertFields(['value' => serialize(NULL)])
        ->keys($key)->execute();
      $query = $this->database->select('key_value', 'r')->fields('r', ['value'])->forUpdate();
      foreach ($key as $field => $value) {
        $query->condition($field, $value);
      }
      $saved = $query->execute()->fetchField();
      if (!is_string($saved)) {
        throw new FigmaException('figma_asset_unavailable', 503);
      }
      $receipt = unserialize($saved, ['allowed_classes' => FALSE]);
      if ($receipt === NULL) {
        $media = $this->upload->fromImageFile($image, $uid, 'Figma asset');
        $file = $media->get('field_media_image')->entity;
        if (!$file instanceof FileInterface) {
          throw new FigmaException('figma_asset_unavailable', 503);
        }
        $receipt = ['media' => (string) $media->id(), 'file' => (string) $file->id(),
          'uri' => $file->getFileUri()];
        $update = $this->database->update('key_value')->fields(['value' => serialize($receipt)]);
        foreach ($key as $field => $value) {
          $update->condition($field, $value);
        }
        $update->execute();
      }
      else {
        if (!is_array($receipt) || array_keys($receipt) !== ['media', 'file', 'uri'] ||
          !is_string($receipt['media']) || !ctype_digit($receipt['media']) ||
          !is_string($receipt['file']) || !ctype_digit($receipt['file']) ||
          !is_string($receipt['uri'])) {
          throw new FigmaException('figma_asset_unavailable', 503);
        }
        $storage = $this->entities->getStorage('media');
        $storage->resetCache([$receipt['media']]);
        $this->entities->getStorage('file')->resetCache([$receipt['file']]);
        $media = $storage->load($receipt['media']);
        $file = $media instanceof MediaInterface && $media->hasField('field_media_image')
          ? $media->get('field_media_image')->entity : NULL;
      }
      // A receipt is not a grant. Recheck current entities and bytes before returning their URL.
      if (!$media instanceof MediaInterface || !$file instanceof FileInterface ||
        (int) $media->getOwnerId() !== $uid || (int) $file->getOwnerId() !== $uid ||
        (string) $file->id() !== $receipt['file'] || $file->getFileUri() !== $receipt['uri'] ||
        !str_starts_with($receipt['uri'], 'public://') ||
        !$media->access('view', $account) ||
        !$media->get('field_media_image')->access('view', $account) ||
        !$file->access('view', $account) ||
        @filesize($receipt['uri']) !== strlen($image->getBinary()) ||
        !hash_equals($digest, @hash_file('sha256', $receipt['uri']) ?: '')) {
        throw new FigmaException('figma_asset_conflict', 409);
      }
      $url = $this->urls->generateAbsoluteString($receipt['uri']);
    }
    catch (\Throwable $error) {
      $transaction->rollBack();
      // Rollback does not clear Drupal's in-process entity caches.
      if (isset($receipt['media'], $receipt['file'])) {
        $this->entities->getStorage('media')->resetCache([$receipt['media']]);
        $this->entities->getStorage('file')->resetCache([$receipt['file']]);
      }
      throw $error;
    }
    unset($transaction);
    return $url;
  }

}
