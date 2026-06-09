<?php

namespace Drupal\ai\OperationType\GenericType;

use Drupal\ai\Traits\File\FileMimeTypeTrait;
use Drupal\ai\Traits\File\GenerateBase64Trait;
use Drupal\ai\Traits\File\GenerateBinaryTrait;
use Drupal\ai\Traits\File\GenerateFileEntityTrait;
use Drupal\ai\Traits\File\GenerateMediaEntityTrait;
use Drupal\file\Entity\File;

/**
 * The file base.
 */
abstract class AbstractFileBase implements FileBaseInterface {

  use FileMimeTypeTrait;
  use GenerateBase64Trait;
  use GenerateBinaryTrait;
  use GenerateFileEntityTrait;
  use GenerateMediaEntityTrait;

  /**
   * The mime type of the file.
   *
   * @var string
   */
  private string $mimeType;

  /**
   * The filename if it exists.
   *
   * @var string
   */
  private string $filename;

  /**
   * The binary of the file.
   *
   * @var string
   */
  private string $binary;

  /**
   * {@inheritdoc}
   */
  public function __construct(string $binary = "", string $mime_type = "", string $filename = "") {
    $this->binary = $binary;
    $this->mimeType = $mime_type;
    $this->filename = $filename;
  }

  /**
   * {@inheritdoc}
   */
  public function getMimeType(): string {
    return $this->mimeType;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilename(): string {
    return $this->filename;
  }

  /**
   * {@inheritdoc}
   */
  public function getBinary(): string {
    return $this->binary;
  }

  /**
   * {@inheritdoc}
   */
  public function getFileType(): string {
    if (str_contains($this->filename, '.')) {
      return substr($this->filename, strrpos($this->filename, '.') + 1);
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function setMimeType(string $mime_type): void {
    $this->mimeType = $mime_type;
  }

  /**
   * {@inheritdoc}
   */
  public function setFilename(string $filename): void {
    $this->filename = $filename;
  }

  /**
   * {@inheritdoc}
   */
  public function setBinary(string $binary): void {
    $this->binary = $binary;
  }

  /**
   * {@inheritdoc}
   */
  public function resetMimeTypeFromFileName(): void {
    $this->mimeType = $this->getFileMimeTypeGuesser()->guessMimeType($this->filename);
  }

  /**
   * {@inheritdoc}
   */
  public function setFileFromUrl(string $url): void {
    // Get mime type from the uri.
    $this->mimeType = $this->getFileMimeTypeGuesser()->guessMimeType($url);
    $this->binary = file_get_contents($url);
    $this->filename = basename($url);
  }

  /**
   * {@inheritdoc}
   */
  public function setFileFromUri(string $uri): void {
    // Get mime type from the uri.
    $this->mimeType = $this->getFileMimeTypeGuesser()->guessMimeType($uri);
    $this->binary = file_get_contents($uri);
    $this->filename = basename($uri);
  }

  /**
   * {@inheritdoc}
   */
  public function setFileFromFile(File $file): void {
    $this->mimeType = $file->getMimeType();
    $this->binary = file_get_contents($file->getFileUri());
    $this->filename = $file->getFilename();
  }

  /**
   * Create an array representation of the file.
   *
   * @return array
   *   The array representation of the file.
   */
  public function toArray(): array {
    return [
      'type' => static::class,
      'base64' => $this->getAsBase64EncodedString(),
      'mime_type' => $this->getMimeType(),
      'filename' => $this->getFilename(),
    ];
  }

  /**
   * Create from array.
   *
   * @param array $data
   *   The data to create the file from.
   */
  public static function fromArray(array $data): static {
    $instance = new static();
    if (isset($data['base64'])) {
      $instance->setFromBase64EncodedString($data['base64']);
    }
    if (isset($data['mime_type'])) {
      $instance->setMimeType($data['mime_type']);
    }
    if (isset($data['filename'])) {
      $instance->setFilename($data['filename']);
    }
    return $instance;
  }

}
