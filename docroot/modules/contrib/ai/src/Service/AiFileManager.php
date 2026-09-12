<?php

namespace Drupal\ai\Service;

use Drupal\ai\AiFileProviderInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Entity\AiFile;
use Drupal\ai\Entity\AiFileInterface;
use Drupal\ai\OperationType\GenericType\FileBaseInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\MimeTypeGuesserInterface;

/**
 * Manager service for AI File lifecycle.
 */
class AiFileManager {

  use StringTranslationTrait;

  public function __construct(
    #[Autowire(service: 'ai.provider')]
    protected readonly AiProviderPluginManager $providerManager,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly LoggerChannelFactoryInterface $loggerFactory,
    #[Autowire(service: 'file.mime_type.guesser')]
    protected readonly MimeTypeGuesserInterface $mimeTypeGuesser,
  ) {}

  /**
   * Get the AI File entity storage.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   The AI File storage.
   */
  protected function getStorage() {
    return $this->entityTypeManager->getStorage('ai_file');
  }

  /**
   * Create and upload a file for a provider from a local path.
   *
   * @param \Drupal\ai\OperationType\GenericType\FileBaseInterface|string $file
   *   The file to upload, either as a FileBaseInterface or local path.
   * @param int $owner_id
   *   The user ID of the owner.
   * @param string $provider_id
   *   The provider plugin ID to use for upload.
   * @param array<string, mixed>|null $metadata
   *   Optional metadata to attach to the file entity.
   * @param string $purpose
   *   The purpose of the file, see AiFileInterface::PURPOSE_*.
   *
   * @return \Drupal\ai\Entity\AiFileInterface|null
   *   The created AI File entity or NULL on failure.
   *
   * @todo Provider should have a default set.
   * @see docroot/modules/custom/ai/src/Form/AiSettingsForm.php
   */
  public function upload(FileBaseInterface|string $file, int $owner_id, string $provider_id, ?array $metadata = [], string $purpose = AiFileInterface::PURPOSE_USER_DATA): ?AiFileInterface {
    if ($file instanceof FileBaseInterface) {
      $filename = $file->getFilename();
      $mime = $file->getMimeType() ?: 'application/octet-stream';

      // Get the filesize.
      $file_contents = $file->getBinary();
      $filesize = strlen($file_contents);
      if ($filesize === 0) {
        $this->loggerFactory->get('ai')
          ->error('Upload error for %f: file is empty', ['%f' => $filename]);
        return NULL;
      }
    }
    elseif (is_string($file) && file_exists($file)) {
      $filename = basename($file);
      $mime = $this->mimeTypeGuesser->guessMimeType($file) ?: 'application/octet-stream';
      $filesize = filesize($file);
      if ($filesize === 0) {
        $this->loggerFactory->get('ai')
          ->error('Upload error for %f: file is empty', ['%f' => $filename]);
        return NULL;
      }
      $file_contents = fopen($file, 'r');
      if ($file_contents === FALSE) {
        $this->loggerFactory->get('ai')
          ->error('Upload error for %f: could not open file', ['%f' => $filename]);
        return NULL;
      }
    }
    else {
      $this->loggerFactory->get('ai')
        ->error('Upload error: invalid file input');
      return NULL;
    }

    /** @var \Drupal\ai\Entity\AiFile $entity */
    $entity = AiFile::create([
      'provider' => $provider_id,
      'filename' => $filename,
      'mime_type' => $mime,
      'size' => $filesize,
      'uid' => $owner_id,
      'purpose' => $purpose,
    ]);
    if ($metadata) {
      $entity->setMetadata($metadata);
    }

    // @todo have default provider in config.
    $provider = $this->providerManager->createInstance($provider_id)->getPlugin();
    if (!$provider instanceof AiFileProviderInterface) {
      $this->loggerFactory->get('ai')
        ->error('Upload error for %f: invalid provider %p', [
          '%f' => $filename,
          '%p' => $provider_id,
        ]);
      return NULL;
    }

    try {
      $provider->uploadFile($entity, $file_contents);
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('ai')->error('Upload error for %f: @m', ['%f' => $filename, '@m' => $e->getMessage()]);
      if (is_resource($file_contents)) {
        fclose($file_contents);
      }
      return NULL;
    }
    if (is_resource($file_contents)) {
      fclose($file_contents);
    }

    // Try to save the entity; if it fails, revert the upload.
    try {
      $entity->save();
      return $entity;
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('ai')
        ->error('Entity save failed for %f, reverting upload: @m', [
          '%f' => $filename,
          '@m' => $e->getMessage(),
        ]);

      // Attempt to delete the orphaned remote file.
      try {
        $provider->deleteFile($entity);
      }
      catch (\Exception $deleteException) {
        $this->loggerFactory->get('ai')
          ->error('Failed to revert upload for %f: @m', [
            '%f' => $filename,
            '@m' => $deleteException->getMessage(),
          ]);
      }

      return NULL;
    }
  }

  /**
   * Delete remote file only (entity deletion handled by form layer).
   *
   * @param \Drupal\ai\Entity\AiFileInterface $ai_file
   *   The AI File entity.
   *
   * @return bool
   *   TRUE if remote deletion was successful.
   */
  public function remoteDelete(AiFileInterface $ai_file): bool {
    $provider = $this->providerManager->createInstance($ai_file->getProvider())->getPlugin();

    if ($provider instanceof AiFileProviderInterface) {
      try {
        $success = $provider->deleteFile($ai_file);
        if ($success) {
          return TRUE;
        }
      }
      catch (\Throwable $e) {
        $this->loggerFactory->get('ai')
          ->error('Remote delete failed for file @id: @m', [
            '@id' => $ai_file->id(),
            '@m' => $e->getMessage(),
          ]);
      }
    }
    return FALSE;
  }

  /**
   * Load AI Files by purpose with optional owner restriction.
   *
   * @param string $purpose
   *   Purpose key.
   * @param int|null $owner_id
   *   Optional user id to restrict.
   * @param int $limit
   *   Max number of results (0 = no limit).
   *
   * @return \Drupal\ai\Entity\AiFileInterface[]
   *   Matching AI File entities.
   */
  public function loadByPurpose(string $purpose, ?int $owner_id = NULL, int $limit = 50): array {
    $storage = $this->getStorage();
    $query = $storage->getQuery()->accessCheck(TRUE)
      ->condition('purpose', $purpose);
    if ($owner_id) {
      $query->condition('uid', $owner_id);
    }
    if ($limit > 0) {
      $query->range(0, $limit);
    }
    $ids = $query->execute();
    if (!$ids) {
      return [];
    }
    return array_filter(
      $storage->loadMultiple($ids),
      fn($entity) => $entity instanceof AiFileInterface
    );
  }

}
