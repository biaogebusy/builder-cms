<?php

namespace Drupal\replication\Encoder;

use Drupal\Component\Utility\Random;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;

class StreamEncoder implements EncoderInterface, DecoderInterface {

  /**
   * @var array
   */
  protected $formats = ['stream', 'base64_stream'];

  /**
   * @param \Drupal\Component\Utility\Random $random
   */
  protected $random;

  public function __construct(Random $random = NULL) {
    $this->random = $random ?: new Random();
  }

  /**
   * {@inheritdoc}
   */
  public function encode($data, $format, array $context = []) {
    if (!is_resource($data)) {
      throw new \InvalidArgumentException(sprintf('Data argument is not a resource.'));
    }
    $contents = stream_get_contents($data);
    return ($format == 'base64_stream') ? base64_encode($contents) : $contents;
  }

  /**
   * {@inheritdoc}
   */
  public function decode($data, $format, array $context = []) {
    if (!is_scalar($data)) {
      throw new \InvalidArgumentException(sprintf('Data argument is not a scalar.'));
    }
    $uri = !empty($context['uri']) ? $context['uri'] : 'temporary://' . $this->random->name();
    $mode = !empty($context['mode']) ? $context['mode'] : 'w+b';
    $stream = fopen($uri, $mode);
    $data = ($format == 'base64_stream') ? base64_decode($data) : $data;
    fwrite($stream, $data);
    // Put the file pointer back to the beginning.
    rewind($stream);
    return $stream;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsEncoding($format) {
    return in_array($format, $this->formats);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDecoding($format) {
    return in_array($format, $this->formats);
  }
}
