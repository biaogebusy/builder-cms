<?php

namespace Drupal\ai\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Defines the interface for AI File content entities.
 *
 * Represents a remotely stored AI provider file reference and optional
 * local file mapping with purpose classification and metadata.
 */
interface AiFileInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Purpose: Assistants API usage (tool / retrieval contexts).
   */
  public const PURPOSE_ASSISTANTS = 'assistants';

  /**
   * Purpose: Batch API processing.
   */
  public const PURPOSE_BATCH = 'batch';

  /**
   * Purpose: Fine-tuning training data.
   */
  public const PURPOSE_FINE_TUNE = 'fine-tune';

  /**
   * Purpose: Vision model ingestion (e.g. images for analysis/fine-tune).
   */
  public const PURPOSE_VISION = 'vision';

  /**
   * Purpose: Generic / user supplied data (default).
   */
  public const PURPOSE_USER_DATA = 'user_data';

  /**
   * Purpose: Evaluation data sets.
   */
  public const PURPOSE_EVALS = 'evals';

  /**
   * Purpose: Rag Storage.
   */
  public const PURPOSE_RAG_STORAGE = 'rag_storage';

  /**
   * Purpose: OCR Processing.
   */
  public const PURPOSE_OCR = 'ocr';

  /**
   * Gets the provider machine name / plugin id.
   *
   * @return string|null
   *   The provider id or NULL if unset.
   */
  public function getProvider(): ?string;

  /**
   * Sets the provider id.
   *
   * @param string $provider
   *   Provider plugin id.
   *
   * @return $this
   */
  public function setProvider(string $provider): self;

  /**
   * Gets the remote provider file identifier.
   *
   * @return string|null
   *   Remote id or NULL if not yet assigned.
   */
  public function getRemoteId(): ?string;

  /**
   * Sets the remote provider file identifier.
   *
   * @param string|null $remote_id
   *   Remote id or NULL to clear.
   *
   * @return $this
   */
  public function setRemoteId(?string $remote_id): self;

  /**
   * Gets the original filename (label).
   *
   * @return string|null
   *   The filename or NULL.
   */
  public function getFilename(): ?string;

  /**
   * Sets the filename (no path component).
   *
   * @param string $filename
   *   The filename.
   *
   * @return $this
   */
  public function setFilename(string $filename): self;

  /**
   * Gets the MIME type.
   *
   * @return string|null
   *   MIME type or NULL.
   */
  public function getMimeType(): ?string;

  /**
   * Sets the MIME type.
   *
   * @param string $mime_type
   *   The MIME type.
   *
   * @return $this
   */
  public function setMimeType(string $mime_type): self;

  /**
   * Gets the file size in bytes.
   *
   * @return int|null
   *   Size in bytes or NULL if unknown.
   */
  public function getFileSize(): ?int;

  /**
   * Sets the file size in bytes.
   *
   * @param int|null $size
   *   Size or NULL.
   *
   * @return $this
   */
  public function setFileSize(?int $size): self;

  /**
   * Gets decoded provider-specific metadata.
   *
   * @return array<string, mixed>
   *   Associative metadata array.
   */
  public function getMetadata(): array;

  /**
   * Replaces metadata entirely.
   *
   * @param array<string, mixed> $metadata
   *   New metadata array.
   *
   * @return $this
   */
  public function setMetadata(array $metadata): self;

  /**
   * Merges new metadata into existing metadata (overwrites duplicate keys).
   *
   * @param array<string, mixed> $metadata
   *   Metadata to merge.
   *
   * @return $this
   */
  public function mergeMetadata(array $metadata): self;

  /**
   * Gets local Drupal file entity id (fid) if linked.
   *
   * @return int|null
   *   Local file id or NULL.
   */
  public function getLocalFileId(): ?int;

  /**
   * Gets the declared purpose.
   *
   * @return string
   *   One of the PURPOSE_* constants.
   */
  public function getPurpose(): string;

  /**
   * Sets the declared purpose.
   *
   * @param string $purpose
   *   One of the PURPOSE_* constants.
   *
   * @return $this
   */
  public function setPurpose(string $purpose): self;

}
