<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation;

/**
 * Base trait for Scale and Smart Crop operations.
 */
trait ScaleAndSmartCropTrait {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return [
      'algorithm' => [
        'description' => 'The calculation algorithm for the crop',
        'required' => TRUE,
      ],
      'algorithm_params' => [
        'description' => 'The calculation algorithm parameters',
        'required' => FALSE,
        'default' => [],
      ],
      'simulate' => [
        'description' => 'Boolean indicating the crop shall not be executed, but just the crop area highlighted on the source image',
        'required' => FALSE,
        'default' => FALSE,
      ],
      'width' => [
        'description' => 'The target width, in pixels',
        'required' => TRUE,
      ],
      'height' => [
        'description' => 'The target height, in pixels',
        'required' => TRUE,
      ],
      'upscale' => [
        'description' => 'Boolean indicating that files smaller than the dimensions will be scaled up. This generally results in a low quality image',
        'required' => FALSE,
        'default' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    $actualWidth = $this->getToolkit()->getWidth();
    $actualHeight = $this->getToolkit()->getHeight();
    $scaleFactor = max($arguments['width'] / $actualWidth, $arguments['height'] / $actualHeight);

    $arguments['resize'] = [
      'width' => (int) round($actualWidth * $scaleFactor),
      'height' => (int) round($actualHeight * $scaleFactor),
    ];

    // Fail when width or height are 0 or negative.
    if ($arguments['width'] <= 0) {
      throw new \InvalidArgumentException("Invalid width ('{$arguments['width']}') specified for the image '{$this->getPluginDefinition()['operation']}' operation");
    }
    if ($arguments['height'] <= 0) {
      throw new \InvalidArgumentException("Invalid height ('{$arguments['height']}') specified for the image '{$this->getPluginDefinition()['operation']}' operation");
    }
    return $arguments;
  }

}
