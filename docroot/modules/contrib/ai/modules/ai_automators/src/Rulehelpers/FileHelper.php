<?php

namespace Drupal\ai_automators\Rulehelpers;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Token;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;

/**
 * Helper functions for generating and storing files.
 */
class FileHelper {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   */
  public EntityTypeManagerInterface $entityTypeManager;

  /**
   * The File System interface.
   */
  public FileSystemInterface $fileSystem;

  /**
   * The file repository.
   */
  public FileRepositoryInterface $fileRepo;

  /**
   * The token system to replace and generate paths.
   */
  public Token $token;

  /**
   * The current user.
   */
  public AccountProxyInterface $currentUser;

  /**
   * Constructor for the class.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system interface.
   * @param \Drupal\file\FileRepositoryInterface $fileRepo
   *   The file repository.
   * @param \Drupal\Core\Utility\Token $token
   *   The token system.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    FileSystemInterface $fileSystem,
    FileRepositoryInterface $fileRepo,
    Token $token,
    AccountProxyInterface $currentUser,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->fileSystem = $fileSystem;
    $this->fileRepo = $fileRepo;
    $this->token = $token;
    $this->currentUser = $currentUser;
  }

  /**
   * Create filepath from field config.
   *
   * @param string $fileName
   *   The file name.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return string
   *   The file path.
   */
  public function createFilePathFromFieldConfig($fileName, FieldDefinitionInterface $fieldDefinition, ContentEntityInterface $entity) {
    $config = $fieldDefinition->getConfig($entity->bundle())->getSettings();
    $path = $this->token->replace($config['uri_scheme'] . '://' . rtrim($config['file_directory'], '/'));
    $this->fileSystem->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY);
    return $path . '/' . $fileName;
  }

  /**
   * Prepare image file entity from a binary.
   *
   * @param string $binary
   *   The binary string.
   * @param string $dest
   *   The destination.
   * @param string $alt_text
   *   The alt text.
   * @param string $title
   *   The title.
   *
   * @return array
   *   The image entity with meta data.
   */
  public function generateImageMetaDataFromBinary(string $binary, string $dest, string $alt_text = '', string $title = '') {
    $file = $this->generateFileFromBinary($binary, $dest);
    if ($file instanceof FileInterface) {
      // Get resolution.
      $resolution = getimagesize($file->uri->value);
      // Add to the entities saved.
      return [
        'target_id' => $file->id(),
        'width' => $resolution[0],
        'height' => $resolution[1],
        'alt' => $alt_text,
        'title' => $title,
      ];
    }
    return NULL;
  }

  /**
   * Generate a file entity from a binary.
   *
   * @param string $binary
   *   The binary string.
   * @param string $dest
   *   The destination.
   *
   * @return \Drupal\file\FileInterface|false
   *   The file or false on failure.
   */
  public function generateFileFromBinary(string $binary, string $dest) {
    $path = substr($dest, 0, -(strlen($dest) + 1));
    // Create directory if not existing.
    $this->fileSystem->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY);
    $file = $this->fileRepo->writeData($binary, $dest, FileExists::Rename);
    if ($file->save()) {
      return $file;
    }
    return FALSE;
  }

  /**
   * Generate a file entity from a url by downloading it.
   *
   * @param string $url
   *   The url.
   * @param string $dest
   *   The destination.
   *
   * @return \Drupal\file\FileInterface|false
   *   The file or false on failure.
   */
  public function generateFileFromUrl(string $url, string $dest) {
    $binary = file_get_contents($url);
    return $this->generateFileFromBinary($binary, $dest);
  }

  /**
   * Generate a temporary file from a binary.
   *
   * @param string $binary
   *   The binary string.
   * @param string $fileType
   *   The file type.
   *
   * @return \Drupal\file\FileInterface|false
   *   The file or false on failure.
   */
  public function generateTemporaryFileFromBinary(string $binary, $fileType = '') {
    $tmpName = $this->fileSystem->tempnam('temporary://', 'ai_automator_');
    if ($fileType) {
      // Delete and generate with a extension.
      unlink($tmpName);
      $tmpName .= '.' . $fileType;
    }
    $file = $this->fileRepo->writeData($binary, $tmpName, FileExists::Rename);
    if ($file->save()) {
      return $file;
    }
    return FALSE;
  }

  /**
   * Get all media bundles as options.
   *
   * @return array
   *   The media bundles.
   */
  public function getMediaBundles() {
    $bundles = [];
    $mediaTypeStorage = $this->entityTypeManager->getStorage('media_type');
    foreach ($mediaTypeStorage->loadMultiple() as $mediaType) {
      $bundles[$mediaType->id()] = $mediaType->label();
    }
    return $bundles;
  }

  /**
   * Get the field definition of the medias field.
   *
   * @param string $mediaType
   *   The media type.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface
   *   The field definition.
   */
  private function getMediaField($mediaType) {
    $mediaStorage = $this->entityTypeManager->getStorage('media');
    $mediaTypeInterface = $this->entityTypeManager->getStorage('media_type')->load($mediaType);
    /** @var \Drupal\media\Entity\Media $media */
    $media = $mediaStorage->create([
      'name' => 'tmp',
      'bundle' => $mediaType,
    ]);
    $sourceField = $media->getSource()->getSourceFieldDefinition($mediaTypeInterface);
    return $sourceField;
  }

  /**
   * Get media settings for a media type.
   *
   * @param string $mediaType
   *   The media type.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return array
   *   The media settings.
   */
  public function getMediaSettings($mediaType, ContentEntityInterface $entity) {
    $fileField = $this->getMediaField($mediaType);
    return $fileField->getConfig($entity->bundle())->getSettings();
  }

  /**
   * Create file path for media from field config.
   *
   * @param string $fileName
   *   The file name.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   * @param string $mediaType
   *   The media type.
   *
   * @return string
   *   The file path.
   */
  public function createMediaFilePathFromFieldConfig($fileName, FieldDefinitionInterface $fieldDefinition, $mediaType) {
    $entity = $this->entityTypeManager->getStorage('media')->create([
      'name' => 'tmp',
      'bundle' => $mediaType,
    ]);
    return $this->createFilePathFromFieldConfig($fileName, $fieldDefinition, $entity);
  }

  /**
   * Generate media from a binary.
   *
   * @param string $fileName
   *   The file name.
   * @param string $binary
   *   The binary string.
   * @param string $mediaType
   *   The media type.
   * @param string $mediaName
   *   The media name.
   * @param array $params
   *   The media params.
   *
   * @return \Drupal\media\Entity\Media|false
   *   The media or false on failure.
   */
  public function generateMediaImageFromFile($fileName, $binary, $mediaType, $mediaName, $params = []) {
    $sourceField = $this->getMediaField($mediaType);
    $fileField = $sourceField->getName();
    $mediaStorage = $this->entityTypeManager->getStorage('media');
    $path = $this->createMediaFilePathFromFieldConfig($fileName, $sourceField, $mediaType);

    $imageConfig = $sourceField->getConfig($mediaType)->getSettings();
    if (!$imageConfig) {
      return [];
    }
    $file = $this->generateFileFromBinary($binary, $path);
    // Get resolution.
    $resolution = getimagesize($file->uri->value);

    // Prepare for Media.
    $fileForMedia = [
      'target_id' => $file->id(),
      'alt' => $params['alt'] ?? '',
      'title' => $params['title'] ?? '',
      'width' => $resolution[0],
      'height' => $resolution[1],
    ];

    /** @var \Drupal\media\Entity\Media */
    $media = $mediaStorage->create([
      'name' => $mediaName,
      'bundle' => $mediaType,
      $fileField => $fileForMedia,
    ]);
    $media->save();

    return $media;
  }

  /**
   * Get the File System interface.
   *
   * @return \Drupal\Core\File\FileSystemInterface
   *   The file system interface.
   */
  public function getFileSystem(): FileSystemInterface {
    return $this->fileSystem;
  }

  /**
   * Get the File Repository interface.
   *
   * @return \Drupal\file\FileRepositoryInterface
   *   The file repository interface.
   */
  public function getFileRepository(): FileRepositoryInterface {
    return $this->fileRepo;
  }

}
