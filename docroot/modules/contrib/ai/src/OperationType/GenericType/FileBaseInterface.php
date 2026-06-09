<?php

namespace Drupal\ai\OperationType\GenericType;

use Drupal\file\Entity\File;
use Symfony\Component\Mime\MimeTypeGuesserInterface;

/**
 * The file base interface.
 */
interface FileBaseInterface {

  /**
   * The constructor.
   *
   * @param string $binary
   *   The binary of the file.
   * @param string $mime_type
   *   The mime type of the file.
   * @param string $filename
   *   The filename of the file.
   */
  public function __construct(string $binary = "", string $mime_type = "", string $filename = "");

  /**
   * Get the mime type.
   *
   * @return string
   *   The mime type.
   */
  public function getMimeType(): string;

  /**
   * Get the filename.
   *
   * @return string
   *   The filename.
   */
  public function getFilename(): string;

  /**
   * Get the binary.
   *
   * @return string
   *   The binary.
   */
  public function getBinary(): string;

  /**
   * Get the file type.
   *
   * @return string
   *   The file type.
   */
  public function getFileType(): string;

  /**
   * Set the mime type.
   *
   * @param string $mime_type
   *   The mime type.
   */
  public function setMimeType(string $mime_type): void;

  /**
   * Set the filename.
   *
   * @param string $filename
   *   The filename.
   */
  public function setFilename(string $filename): void;

  /**
   * Set the binary.
   *
   * @param string $binary
   *   The binary.
   */
  public function setBinary(string $binary): void;

  /**
   * Sets the file from an url.
   *
   * @param string $url
   *   The url.
   */
  public function setFileFromUrl(string $url): void;

  /**
   * Set the file from a Drupal uri.
   *
   * @param string $uri
   *   The uri.
   */
  public function setFileFromUri(string $uri): void;

  /**
   * Sets the file from a Drupal file.
   *
   * @param \Drupal\file\Entity\File $file
   *   The file.
   */
  public function setFileFromFile(File $file): void;

  /**
   * Get the file mime type guesser.
   *
   * @return \Symfony\Component\Mime\MimeTypeGuesserInterface
   *   The stream wrapper.
   */
  public function getFileMimeTypeGuesser(): MimeTypeGuesserInterface;

}
