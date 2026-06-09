<?php

namespace Drupal\ai\OperationType\ImageToImage;

use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\InputBase;
use Drupal\ai\OperationType\InputInterface;

/**
 * Input object for image to image input.
 */
class ImageToImageInput extends InputBase implements InputInterface {

  /**
   * The image file to use as input.
   *
   * @var \Drupal\ai\OperationType\GenericType\ImageFile
   */
  private ImageFile $file;

  /**
   * Set a possible prompt.
   *
   * @var string|null
   */
  private ?string $prompt = NULL;

  /**
   * Set a possible mask image.
   *
   * @var \Drupal\ai\OperationType\GenericType\ImageFile|null
   */
  private ?ImageFile $mask = NULL;

  /**
   * The constructor.
   *
   * @param \Drupal\ai\OperationType\GenericType\ImageFile $file
   *   The audio file to convert.
   */
  public function __construct(ImageFile $file) {
    $this->file = $file;
  }

  /**
   * Get the image binary to convert into another binary.
   *
   * @return \Drupal\ai\OperationType\GenericType\ImageFile
   *   The binary.
   */
  public function getImageFile(): ImageFile {
    return $this->file;
  }

  /**
   * Set the image file.
   *
   * @param \Drupal\ai\OperationType\GenericType\ImageFile $file
   *   The image file.
   */
  public function setImageFile(ImageFile $file) {
    $this->file = $file;
  }

  /**
   * Get the prompt for the image to image operation.
   *
   * @return string|null
   *   The prompt, or NULL if not set.
   */
  public function getPrompt(): ?string {
    return $this->prompt;
  }

  /**
   * Set the prompt for the image to image operation.
   *
   * @param string|null $prompt
   *   The prompt to set, or NULL to unset.
   */
  public function setPrompt(?string $prompt): void {
    $this->prompt = $prompt;
  }

  /**
   * Get the mask image for the image to image operation.
   *
   * @return \Drupal\ai\OperationType\GenericType\ImageFile|null
   *   The mask image, or NULL if not set.
   */
  public function getMask(): ?ImageFile {
    return $this->mask;
  }

  /**
   * Set the mask image for the image to image operation.
   *
   * @param \Drupal\ai\OperationType\GenericType\ImageFile|null $mask
   *   The mask image to set, or NULL to unset.
   */
  public function setMask(?ImageFile $mask): void {
    $this->mask = $mask;
  }

  /**
   * {@inheritdoc}
   */
  public function toString(): string {
    return $this->file->getFilename();
  }

  /**
   * Return the input as string.
   *
   * @return string
   *   The input as string.
   */
  public function __toString(): string {
    return $this->toString();
  }

}
