<?php

namespace Drupal\ai\OperationType\Embeddings;

use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\InputBase;
use Drupal\ai\OperationType\InputInterface;

/**
 * Input object for embeddings input.
 */
class EmbeddingsInput extends InputBase implements InputInterface {

  /**
   * The prompts to convert to vectors.
   *
   * @var string
   */
  private string $prompt;

  /**
   * If its an image to convert to vectors.
   *
   * @var \Drupal\ai\OperationType\GenericType\ImageFile|null
   */
  private ImageFile|NULL $image;

  /**
   * The constructor.
   *
   * @param string $prompt
   *   The prompt to convert to vectors.
   * @param \Drupal\ai\OperationType\GenericType\ImageFile $image
   *   The image to convert to vectors.
   */
  public function __construct(string $prompt = '', ?ImageFile $image = NULL) {
    $this->prompt = $prompt;
    $this->image = $image;
  }

  /**
   * Get the prompt.
   *
   * @return string
   *   The prompt.
   */
  public function getPrompt(): string {
    return $this->prompt;
  }

  /**
   * Get the image.
   *
   * @return \Drupal\ai\OperationType\GenericType\ImageFile
   *   The image.
   */
  public function getImage(): ImageFile|null {
    return $this->image;
  }

  /**
   * Set the prompt.
   *
   * @param string $prompt
   *   The prompt.
   */
  public function setPrompt(string $prompt) {
    $this->prompt = $prompt;
  }

  /**
   * Set the image.
   *
   * @param \Drupal\ai\OperationType\GenericType\ImageFile $image
   *   The image.
   */
  public function setImage(ImageFile $image) {
    $this->image = $image;
  }

  /**
   * {@inheritdoc}
   */
  public function toString(): string {
    return $this->prompt;
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

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    $data = [
      'prompt' => $this->prompt,
    ];
    if ($this->image) {
      $data['image'] = $this->image->toArray();
    }
    return $data;
  }

}
