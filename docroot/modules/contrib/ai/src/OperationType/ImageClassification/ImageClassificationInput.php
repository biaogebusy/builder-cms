<?php

namespace Drupal\ai\OperationType\ImageClassification;

use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\InputBase;
use Drupal\ai\OperationType\InputInterface;

/**
 * Input object for image classification.
 */
class ImageClassificationInput extends InputBase implements InputInterface {

  /**
   * The image file to classify.
   *
   * @var \Drupal\ai\OperationType\GenericType\ImageFile
   */
  private ImageFile $file;

  /**
   * The (in certain cases optional) labels to filter the classification.
   *
   * @var string[]
   */
  private array $labels = [];

  /**
   * The constructor.
   *
   * @param \Drupal\ai\OperationType\GenericType\ImageFile $file
   *   The image file to classify.
   * @param string[] $labels
   *   The (in certain cases optional) labels to filter the classification.
   */
  public function __construct(ImageFile $file, array $labels = []) {
    $this->file = $file;
    $this->labels = $labels;
  }

  /**
   * Get the image that will be classify.
   *
   * @return \Drupal\ai\OperationType\GenericType\ImageFile
   *   The binary.
   */
  public function getImageFile(): ImageFile {
    return $this->file;
  }

  /**
   * Get the labels to filter the classification.
   *
   * @return string[]
   *   The labels to filter the classification.
   */
  public function getLabels(): array {
    return $this->labels;
  }

  /**
   * Set the image file to classify.
   *
   * @param \Drupal\ai\OperationType\GenericType\ImageFile $file
   *   The image file to classify.
   */
  public function setImageFile(ImageFile $file) {
    $this->file = $file;
  }

  /**
   * Set the labels to filter the classification.
   *
   * @param string[] $labels
   *   The labels to filter the classification.
   */
  public function setLabels(array $labels) {
    $this->labels = $labels;
  }

  /**
   * {@inheritdoc}
   */
  public function toString(): string {
    return $this->file->getFilename();
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return [
      'file' => $this->file->toArray(),
      'labels' => $this->labels,
    ];
  }

}
