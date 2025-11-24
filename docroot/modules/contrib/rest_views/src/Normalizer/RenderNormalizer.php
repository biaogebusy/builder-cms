<?php

namespace Drupal\rest_views\Normalizer;

use Drupal\Core\Render\RendererInterface;
use Drupal\rest_views\RenderableData;
use Drupal\serialization\Normalizer\NormalizerBase;

/**
 * Unwrap a RenderableData object and render the element inside.
 *
 * @package Drupal\rest_views\Normalizer
 */
class RenderNormalizer extends NormalizerBase {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * DataNormalizer constructor.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(RendererInterface $renderer) {
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedTypes(?string $format): array {
    return [
      RenderableData::class => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function normalize($object, $format = NULL, array $context = []): float|int|bool|\ArrayObject|array|string|null {
    /** @var \Drupal\rest_views\SerializedData $object */
    /** @var \Symfony\Component\Serializer\Normalizer\NormalizerInterface $normalizer */
    $normalizer = $this->serializer;
    $data = $object->getData();
    return $normalizer->normalize($this->renderer->render($data));
  }

}
