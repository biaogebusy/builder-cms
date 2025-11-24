<?php
namespace Drupal\entity_theme_engine\Encoder;

use Symfony\Component\Serializer\Encoder\EncoderInterface;

/**
 * Denormalizes field item object structure by updating the entity field values.
 */
class TwigVariablesEncoder implements EncoderInterface {

  /**
   * The formats that this Encoder supports.
   *
   * @var string
   */
  protected $format = 'twig_variable';

  /**
   * {@inheritdoc}
   */
  public function supportsEncoding($format): bool {
    return $format == $this->format;
  }

  /**
   *
   * {@inheritdoc}
   */
  public function supportsDecoding($format) {
    return $format == $this->format;
  }

  /**
   *
   * {@inheritdoc}
   */
  public function encode(mixed $data, string $format, array $context = []): string {
    return $data;
  }
}
