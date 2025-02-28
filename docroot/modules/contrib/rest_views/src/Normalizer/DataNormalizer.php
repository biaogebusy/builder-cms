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
   * The interface or class that this Normalizer supports.
   *
   * @var array
   */
  protected $supportedInterfaceOrClass = [SerializedData::class];

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    /** @var \Drupal\rest_views\SerializedData $object */
    /** @var \Symfony\Component\Serializer\Normalizer\NormalizerInterface $normalizer */
    $normalizer = $this->serializer;
    return $normalizer->normalize($object->getData());
  }

}
