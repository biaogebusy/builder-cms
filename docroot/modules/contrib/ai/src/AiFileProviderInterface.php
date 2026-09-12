<?php

namespace Drupal\ai;

use Drupal\ai\Entity\AiFileInterface;

/**
 * AI Provider file capabilities (upload, delete, download, attach).
 */
interface AiFileProviderInterface {

  /**
   * Upload a local file to the remote AI provider.
   *
   * Implementations MUST set the remote id on the entity and MAY set
   * provider-specific metadata. Caller saves the entity.
   *
   * @param \Drupal\ai\Entity\AiFileInterface $ai_file
   *   The AI File entity (unsaved or saved) containing initial data.
   * @param string|resource $file
   *   The binary data of the file to upload.
   *
   * @return \Drupal\ai\Entity\AiFileInterface
   *   The updated (unsaved) entity.
   *
   * @throws \Exception
   *   On upload errors. The caller will handle logging & status updates.
   */
  public function uploadFile(AiFileInterface $ai_file, mixed $file): AiFileInterface;

  /**
   * Delete a remote file (id taken from entity remote id).
   *
   * @param \Drupal\ai\Entity\AiFileInterface $ai_file
   *   The AI File entity.
   *
   * @return bool
   *   TRUE on success, FALSE on a soft failure.
   */
  public function deleteFile(AiFileInterface $ai_file): bool;

  /**
   * Download remote file to a local path or return a path / data string.
   *
   * @param \Drupal\ai\Entity\AiFileInterface $ai_file
   *   The AI File entity.
   * @param string|null $destination
   *   Optional absolute local destination path (directory or full path).
   *
   * @return string
   *   The absolute path to the downloaded file OR the raw contents if a
   *   destination is not provided (implementation dependent, but SHOULD
   *   prefer returning a path when destination provided).
   */
  public function downloadFile(AiFileInterface $ai_file, ?string $destination = NULL): string;

  /**
   * Return TRUE if mime type is supported for upload.
   *
   * @param string $mime_type
   *   The mime type.
   * @param string $purpose
   *   The declared purpose (e.g. fine-tune, assistants, user_data, etc.).
   *
   * @return bool
   *   TRUE if supported.
   */
  public function supportsMimeType(string $mime_type, string $purpose = AiFileInterface::PURPOSE_USER_DATA): bool;

}
