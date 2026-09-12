<?php

namespace Drupal\ai\Traits\OpenAi;

use Drupal\ai\Entity\AiFileInterface;
use Drupal\ai\Exception\AiQuotaException;
use Drupal\ai\Exception\AiRateLimitException;
use OpenAI\Client;

/**
 * AI Provider file capabilities (upload, delete, download, attach).
 */
trait FileApiTrait {

  /**
   * Get the OpenAI client.
   *
   * @return \OpenAI\Client
   *   The client.
   */
  abstract protected function getClient(): Client;

  /**
   * {@inheritdoc}
   */
  public function uploadFile(AiFileInterface $ai_file, mixed $file): AiFileInterface {
    $mime = $ai_file->getMimeType();
    $purpose = $ai_file->getPurpose();
    if (!$this->supportsMimeType($mime, $purpose)) {
      throw new \RuntimeException(sprintf('MIME type %s not supported for purpose %s', $mime, $purpose));
    }

    $payload = [
      'file' => $file,
      'purpose' => $purpose,
    ];

    // Pass through any metadata that might be relevant to upload.
    // E.g. OpenAI supports "expires_after" (in seconds).
    $metadata = $ai_file->getMetadata();
    if (isset($metadata['expires_after'])) {
      $payload['expires_after'] = $metadata['expires_after'];
    }

    try {
      $response = $this->getClient()->files()->upload($payload);
    }
    catch (\Exception $e) {
      // Try to figure out rate limit issues.
      if (strpos($e->getMessage(), 'Request too large') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      if (strpos($e->getMessage(), 'Too Many Requests') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      // Try to figure out quota issues.
      if (strpos($e->getMessage(), 'You exceeded your current quota') !== FALSE) {
        throw new AiQuotaException($e->getMessage());
      }
      else {
        throw $e;
      }
    }

    $ai_file->setRemoteId($response->id);

    // Save the rest to meta in case some crazy person needs it.
    $ai_file->mergeMetadata($response->toArray());

    return $ai_file;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteFile(AiFileInterface $ai_file): bool {
    // No remote id, nothing to do.
    $remote_id = $ai_file->getRemoteId();
    if (empty($remote_id)) {
      return TRUE;
    }

    try {
      $response = $this->getClient()->files()->delete($remote_id);
      return !empty($response->deleted);
    }
    catch (\Exception $e) {
      // @todo log?
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function downloadFile(AiFileInterface $ai_file, ?string $destination = NULL): string {
    // No remote id, nothing to do.
    $remote_id = $ai_file->getRemoteId();
    if (empty($remote_id)) {
      throw new \RuntimeException('AI File has no remote ID, cannot download.');
    }
    $content = $this->getClient()->files()->download($remote_id);
    if ($destination) {
      // If destination is a directory, append the filename.
      if (is_dir($destination)) {
        $filename = $ai_file->getFilename() ?: $remote_id;
        $destination = rtrim($destination, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
      }
      file_put_contents($destination, $content);
      return $destination;
    }
    else {
      return $content;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function supportsMimeType(string $mime_type, string $purpose = AiFileInterface::PURPOSE_USER_DATA): bool {
    // Batch and Fine-tune only support text and jsonl.
    if (in_array($purpose, [AiFileInterface::PURPOSE_BATCH, AiFileInterface::PURPOSE_FINE_TUNE], TRUE)) {
      return in_array($mime_type, ['text/plain', 'application/jsonl', 'application/json'], TRUE);
    }
    // Vision only supports images.
    if ($purpose === AiFileInterface::PURPOSE_VISION) {
      return in_array($mime_type, [
        'image/png',
        'image/jpeg',
        'image/jpg',
        'image/bmp',
        'image/gif',
        'image/tiff',
      ], TRUE);
    }
    // Everything else supports anything.
    return TRUE;
  }

}
