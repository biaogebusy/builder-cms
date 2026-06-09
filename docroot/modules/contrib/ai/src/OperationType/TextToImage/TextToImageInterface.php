<?php

namespace Drupal\ai\OperationType\TextToImage;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\OperationType;
use Drupal\ai\OperationType\OperationTypeInterface;

/**
 * Interface for text to speech models.
 */
#[OperationType(
  id: 'text_to_image',
  label: new TranslatableMarkup('Text To Image'),
  description: new TranslatableMarkup('Generate images from text descriptions.')
)]
interface TextToImageInterface extends OperationTypeInterface {

  /**
   * Generate image from text.
   *
   * @param string|\Drupal\ai\Operation\TextToImage\TextToImageInput $input
   *   The text to generate images from or a Output.
   * @param string $model_id
   *   The model id to use.
   * @param array $tags
   *   Extra tags to set.
   *
   * @return \Drupal\ai\OperationType\TextToImage\TextToImageOutput
   *   The output Output.
   */
  public function textToImage(string|TextToImageInput $input, string $model_id, array $tags = []): TextToImageOutput;

}
