<?php

namespace Drupal\rest_views\Normalizer;

use Drupal\rest_views\SerializedData;
use Drupal\serialization\Normalizer\NormalizerBase;

/**
 * Unwrap a SerializedData object and normalize the data inside.
 *
 * @see \Drupal\rest_views\SerializedData
 */
class DataNormalizer extends NormalizerBase {

  /**
   * {@inheritdoc}
   */
  public function getSupportedTypes(?string $format): array {
    return [
      SerializedData::class => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []): float|int|bool|\ArrayObject|array|string|null {
    /** @var \Drupal\rest_views\SerializedData $object */
    /** @var \Symfony\Component\Serializer\Normalizer\NormalizerInterface $normalizer */
    $normalizer = $this->serializer;
    return $normalizer->normalize($object->getData());
  }

}
