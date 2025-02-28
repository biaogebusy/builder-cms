<?php

namespace Drupal\jsonapi_extras;

use Drupal\jsonapi\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * A decorated JSON:API serializer, with lazily initialized fallback serializer.
 */
class SerializerDecorator implements SerializerInterface, NormalizerInterface, DenormalizerInterface, EncoderInterface, DecoderInterface {

  /**
   * The decorated JSON:API serializer service.
   *
   * @var \Drupal\jsonapi\Serializer\Serializer
   */
  protected $decoratedSerializer;

  /**
   * Whether the lazy dependency has been initialized.
   *
   * @var bool
   */
  protected $isInitialized = FALSE;

  /**
   * Constructs a SerializerDecorator.
   *
   * @param \Drupal\jsonapi\Serializer\Serializer $serializer
   *   The decorated JSON:API serializer.
   */
  public function __construct(Serializer $serializer) {
    $this->decoratedSerializer = $serializer;
  }

  /**
   * Lazily initializes the fallback serializer for the JSON:API serializer.
   *
   * Breaks circular dependency.
   */
  protected function lazilyInitialize() {
    if (!$this->isInitialized) {
      $core_serializer = \Drupal::service('serializer');
      $this->decoratedSerializer->setFallbackNormalizer($core_serializer);
      $this->isInitialized = TRUE;
    }
  }

  /**
   * Relays a method call to the decorated service.
   *
   * @param string $method_name
   *   The method to invoke on the decorated serializer.
   * @param array $args
   *   The arguments to pass to the invoked method on the decorated serializer.
   *
   * @return mixed
   *   The return value.
   */
  protected function relay($method_name, array $args) {
    $this->lazilyInitialize();
    return call_user_func_array([$this->decoratedSerializer, $method_name], $args);
  }

  /**
   * {@inheritdoc}
   */
  public function decode($data, $format, array $context = []) {
    return $this->relay(__FUNCTION__, func_get_args());
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    return $this->relay(__FUNCTION__, func_get_args());
  }

  /**
   * {@inheritdoc}
   */
  public function deserialize($data, $type, $format, array $context = []) {
    return $this->relay(__FUNCTION__, func_get_args());
  }

  /**
   * {@inheritdoc}
   */
  public function encode($data, $format, array $context = []) {
    return $this->relay(__FUNCTION__, func_get_args());
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    return $this->relay(__FUNCTION__, func_get_args());
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDecoding($format) {
    return $this->relay(__FUNCTION__, func_get_args());
  }

  /**
   * {@inheritdoc}
   */
  public function serialize($data, $format, array $context = []) {
    return $this->relay(__FUNCTION__, func_get_args());
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDenormalization($data, $type, $format = NULL) {
    return $this->relay(__FUNCTION__, func_get_args());
  }

  /**
   * {@inheritdoc}
   */
  public function supportsEncoding($format) {
    return $this->relay(__FUNCTION__, func_get_args());
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    return $this->relay(__FUNCTION__, func_get_args());
  }

}
