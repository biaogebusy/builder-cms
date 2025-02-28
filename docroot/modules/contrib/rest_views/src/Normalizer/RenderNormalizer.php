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
   * The interface or class that this Normalizer supports.
   *
   * @var array
   */
  protected $supportedInterfaceOrClass = [RenderableData::class];

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

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
   *
   * @throws \Exception
   */
  public function normalize($object, $format = NULL, array $context = []) {
    /** @var \Drupal\rest_views\SerializedData $object */
    /** @var \Symfony\Component\Serializer\Normalizer\NormalizerInterface $normalizer */
    $normalizer = $this->serializer;
    $data = $object->getData();
    return $normalizer->normalize($this->renderer->render($data));
  }

}
