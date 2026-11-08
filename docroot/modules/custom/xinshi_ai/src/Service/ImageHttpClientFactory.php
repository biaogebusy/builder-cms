<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use GuzzleHttp\Client;

/** Adds image response normalization to the shared public transport. */
class ImageHttpClientFactory extends PublicHttpClientFactory {

  public function create(array $options = [], int $limit = self::MAX_RESPONSE_BYTES): Client {
    $client = parent::create($options, $limit);
    // Keep image SDK compatibility out of ordinary page responses.
    $client->getConfig('handler')->push(OpenAiImageUsageShape::middleware(), OpenAiImageUsageShape::MIDDLEWARE);
    return $client;
  }

}
