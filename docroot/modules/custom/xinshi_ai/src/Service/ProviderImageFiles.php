<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\xinshi_ai\Exception\ImageSafetyException;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Decodes provider image results without trusting URLs, MIME or filenames.
 */
class ProviderImageFiles {

  public function __construct(private readonly ImageHttpClientFactory $http, private readonly ImageFileValidator $validator) {}

  public function fromData(array $data): ImageFile {
    if (isset($data['b64_json']) && is_string($data['b64_json'])) {
      return $this->validator->fromBase64($data['b64_json']);
    }
    if (!is_string($data['url'] ?? NULL)) {
      throw new ImageSafetyException('Provider image is missing data.');
    }
    // This client has no provider Authorization header or site cookies.
    try {
      $response = $this->http->create(['timeout' => 30], ImageFileValidator::MAX_BYTES)->get($data['url']);
    }
    catch (GuzzleException) {
      // Signed asset URLs and response bodies must not reach job errors or logs.
      throw new ImageSafetyException('Remote image download failed.');
    }
    if ($response->getStatusCode() !== 200) {
      throw new ImageSafetyException('Image download did not return HTTP 200.');
    }
    return $this->validator->fromBinary((string) $response->getBody());
  }

}
