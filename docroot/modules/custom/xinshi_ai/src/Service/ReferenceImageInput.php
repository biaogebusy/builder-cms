<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\ai\OperationType\ImageToImage\ImageToImageInput;

/** Carries ordered image references through the existing image-to-image provider API. */
final class ReferenceImageInput extends ImageToImageInput {

  /** @param \Drupal\ai\OperationType\GenericType\ImageFile[] $images */
  public function __construct(private readonly array $images) {
    if (!$images || count($images) > 8) {
      throw new \InvalidArgumentException('Expected one to eight reference images.');
    }
    parent::__construct($images[0]);
  }

  /** @return \Drupal\ai\OperationType\GenericType\ImageFile[] */
  public function getImages(): array {
    return $this->images;
  }

}
