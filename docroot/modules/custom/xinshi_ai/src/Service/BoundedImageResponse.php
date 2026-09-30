<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\xinshi_ai\Exception\ImageSafetyException;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use GuzzleHttp\Psr7\Utils;

/**
 * Caps bytes while cURL writes, including bodies without Content-Length.
 */
final class BoundedImageResponse implements \Psr\Http\Message\StreamInterface {

  use StreamDecoratorTrait;

  private \Psr\Http\Message\StreamInterface $stream;

  public function __construct(private readonly int $limit) {
    $this->stream = Utils::streamFor(fopen('php://temp', 'w+b'));
  }

  public function write(string $string): int {
    if ($this->stream->tell() + strlen($string) > $this->limit) {
      throw new ImageSafetyException('Remote response exceeds the byte limit.');
    }
    return $this->stream->write($string);
  }

}
