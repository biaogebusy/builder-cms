<?php

namespace Drupal\ai\OperationType\ImageToImage;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\OperationType;
use Drupal\ai\OperationType\OperationTypeInterface;

/**
 * Interface for image to image models.
 */
#[OperationType(
  id: 'image_to_image',
  label: new TranslatableMarkup('Image to Image'),
  description: new TranslatableMarkup('Transform images by applying styles, enhancing quality, or making AI-powered edits.')
)]
interface ImageToImageInterface extends OperationTypeInterface {

  /**
   * Generate image from image.
   *
   * @param string|array|\Drupal\ai\Operation\ImageToImage\ImageToImageInput $input
   *   The image to generate image from or a binary.
   * @param string $model_id
   *   The model id to use.
   * @param array $tags
   *   Extra tags to set.
   *
   * @return \Drupal\ai\OperationType\ImageToImage\ImageToImageOutput
   *   The image output object.
   */
  public function imageToImage(string|array|ImageToImageInput $input, string $model_id, array $tags = []): ImageToImageOutput;

  /**
   * Checks if the model requires a mask image.
   *
   * @return bool
   *   Returns TRUE if a mask image is required, FALSE otherwise.
   */
  public function requiresImageToImageMask(string $model_id): bool;

  /**
   * Checks if the model has a mask image.
   *
   * @return bool
   *   Returns TRUE if a mask image is available, FALSE otherwise.
   */
  public function hasImageToImageMask(string $model_id): bool;

  /**
   * Checks if the model required a prompt.
   *
   * @return bool
   *   Returns TRUE if a prompt is required, FALSE otherwise.
   */
  public function requiresImageToImagePrompt(string $model_id): bool;

  /**
   * Checks if the model has a prompt.
   *
   * @return bool
   *   Returns TRUE if a prompt is available, FALSE otherwise.
   */
  public function hasImageToImagePrompt(string $model_id): bool;

}
