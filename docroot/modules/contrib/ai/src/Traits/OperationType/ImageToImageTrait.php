<?php

namespace Drupal\ai\Traits\OperationType;

/**
 * Chat specific base methods.
 *
 * @package Drupal\ai\Traits\OperationType
 */
trait ImageToImageTrait {

  /**
   * {@inheritdoc}
   */
  public function requiresImageToImageMask(string $model_id): bool {
    // Default implementation assumes no mask is required.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function hasImageToImageMask(string $model_id): bool {
    // Default implementation does not assume a mask is available.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function requiresImageToImagePrompt(string $model_id): bool {
    // Default implementation assumes a prompt is required.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function hasImageToImagePrompt(string $model_id): bool {
    // Default implementation assumes a prompt is available.
    return TRUE;
  }

}
