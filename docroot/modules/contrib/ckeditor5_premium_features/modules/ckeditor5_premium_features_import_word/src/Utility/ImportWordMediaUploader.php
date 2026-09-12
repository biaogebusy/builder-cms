<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_import_word\Utility;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\editor\EditorInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;

/**
 * Helper for upload image and creating new media entity.
 */
class ImportWordMediaUploader {

  const DEFAULT_UPLOAD_DIR = 'cke5-word-import-images';

  /**
   * Constructs a new ImportWordMediaUploader.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager,
                              protected FileSystemInterface $fileSystem,
                              protected LockBackendInterface $lock,
                              protected AccountInterface $currentUser) {
  }

  /**
   * Handles media entity creation.
   *
   * @param \Drupal\editor\EditorInterface $editor
   *   The editor.
   * @param string $base64Content
   *   Base64 representation of the image.
   *
   * @return string
   *   The media uuid.
   *
   * @throws \Exception
   */
  public function createMedia(EditorInterface $editor, string $base64Content): string {
    $settings = $editor->getSettings();
    if (!in_array('importWord', $settings['toolbar']['items'], TRUE)) {
      throw new \Exception('Import Word plugin not enabled', 400);
    }
    if (empty($settings['plugins']['ckeditor5_premium_features_import_word__import_word']['upload_media']['enabled'])) {
      throw new \Exception('Import Word plugin not enabled', 400);
    }
    $config = $settings['plugins']['ckeditor5_premium_features_import_word__import_word']['upload_media'];
    $uploadDir = $config['image_destination_dir'] ?? self::DEFAULT_UPLOAD_DIR;

    if (empty($config['media_bundle'])) {
      throw new \Exception('No media bundle provided', 400);
    }
    $bundle = $config['media_bundle'];
    if (empty($config['media_field_name'])) {
      throw new \Exception('No media field provided', 400);
    };
    $mediaFileFieldName = $config['media_field_name'];

    $filePath = $this->prepareFile($base64Content, $uploadDir);
    $file = $this->createFileEntity($filePath);

    $media = $this->createMediaEntity($bundle, $mediaFileFieldName, $file);

    return $media->uuid();
  }

  /**
   * Creates new file entity.
   *
   * @param string $filePath
   *   File path.
   *
   * @return \Drupal\file\FileInterface
   *   New file entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function createFileEntity(string $filePath): FileInterface {
    $fileStorage = $this->entityTypeManager->getStorage('file');
    /**
     * @var \Drupal\file\FileInterface $file
     */
    $file = $fileStorage->create([
      'uri' => $filePath,
      'status' => 1,
      'uid' => $this->currentUser->id(),
    ]);
    $file->save();
    return $file;
  }

  /**
   * Creates new media entity.
   *
   * @param string $bundle
   *   Media bundle.
   * @param string $mediaFileFieldName
   *   Field name in media bundle.
   * @param \Drupal\file\FileInterface $file
   *   File entity.
   *
   * @return \Drupal\media\MediaInterface
   *   Created media entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function createMediaEntity(string $bundle, string $mediaFileFieldName, FileInterface $file): MediaInterface {
    $mediaStorage = $this->entityTypeManager->getStorage('media');
    /**
     * @var \Drupal\media\MediaInterface $media
     */
    $media = $mediaStorage->create([
      'name' => 'import-word-file-' . $file->getFilename(),
      'bundle' => $bundle,
      $mediaFileFieldName => $file->id(),
    ]);
    $media->save();
    return $media;
  }

  /**
   * Convert base64 into image file.
   *
   * @param string $base64Content
   *   Base64 image representation.
   * @param string $destinationDir
   *   Destination where the image will be put.
   *
   * @return string
   *   Path to the image.
   *
   * @throws \Exception
   */
  private function prepareFile(string $base64Content, string $destinationDir):string {
    $imageContent = file_get_contents($base64Content);
    if (!preg_match('/image\/(.*?)\;/', $base64Content, $matches)) {
      throw new \Exception('Wrong base64 data provided', 400);
    }
    $fileExtension = $matches[1];
    $filename = 'image_' . bin2hex(random_bytes(5)) . '.' . $fileExtension;
    $destination = "public://{$destinationDir}/";
    $this->fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY);
    $filePath = $destination . $filename;
    file_put_contents($filePath, $imageContent);
    return $filePath;
  }

}
