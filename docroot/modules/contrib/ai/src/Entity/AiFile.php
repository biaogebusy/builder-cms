<?php

namespace Drupal\ai\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the AI File entity.
 *
 * @ContentEntityType(
 *   id = "ai_file",
 *   label = @Translation("AI File"),
 *   label_collection = @Translation("AI Files"),
 *   handlers = {
 *     "list_builder" = "Drupal\ai\Entity\ListBuilder\AiFileListBuilder",
 *     "access" = "Drupal\ai\Entity\Access\AiFileAccessControlHandler",
 *     "form" = {
 *       "delete" = "Drupal\ai\Form\AiFileDeleteForm"
 *     }
 *   },
 *   base_table = "ai_file",
 *   admin_permission = "administer ai",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *     "label" = "filename"
 *   },
 *   links = {
 *     "delete-form" = "/admin/config/ai/files/{ai_file}/delete",
 *     "collection" = "/admin/config/ai/files"
 *   },
 *   field_ui_base_route = "entity.ai_file.collection"
 * )
 */
class AiFile extends ContentEntityBase implements AiFileInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['provider'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Provider'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 128)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ]);

    $fields['remote_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Remote ID'))
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 1,
      ]);

    $fields['filename'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Filename'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 2,
      ]);

    $fields['mime_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('MIME type'))
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => 3,
      ]);

    $fields['size'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Size (bytes)'))
      ->setSetting('unsigned', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_integer',
        'weight' => 4,
      ]);

    $fields['purpose'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Purpose'))
      ->setDescription(t('Intended purpose for the file (e.g. assistants, batch, fine-tune, vision, user_data, evals).'))
      ->setSetting('max_length', 32)
      ->setDefaultValue(AiFileInterface::PURPOSE_USER_DATA)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 5,
      ]);

    // Metadata stored as JSON encoded long text.
    $fields['metadata'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Metadata'))
      ->setDescription(t('Provider specific metadata stored as JSON.'))
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string_long',
        'weight' => 6,
      ]);

    $fields['local_file'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Local file'))
      ->setDescription(t('Optional local Drupal file entity reference.'))
      ->setSetting('target_type', 'file')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 7,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getProvider(): ?string {
    return $this->get('provider')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setProvider(string $provider): self {
    $this->set('provider', $provider);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteId(): ?string {
    return $this->get('remote_id')->value ?: NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setRemoteId(?string $remote_id): self {
    $this->set('remote_id', $remote_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilename(): ?string {
    return $this->get('filename')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setFilename(string $filename): self {
    $this->set('filename', $filename);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMimeType(): ?string {
    return $this->get('mime_type')->value ?: NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setMimeType(string $mime_type): self {
    $this->set('mime_type', $mime_type);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFileSize(): ?int {
    return $this->get('size')->value !== NULL ? (int) $this->get('size')->value : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setFileSize(?int $size): self {
    $this->set('size', $size);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPurpose(): string {
    return $this->get('purpose')->value ?? AiFileInterface::PURPOSE_USER_DATA;
  }

  /**
   * {@inheritdoc}
   */
  public function setPurpose(string $purpose): self {
    $this->validatePurpose($purpose);
    $this->set('purpose', $purpose);
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   Decoded metadata.
   */
  public function getMetadata(): array {
    $raw = $this->get('metadata')->value;
    if (!$raw) {
      return [];
    }
    $decoded = json_decode($raw, TRUE);
    return is_array($decoded) ? $decoded : [];
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $metadata
   *   New metadata array.
   */
  public function setMetadata(array $metadata): self {
    $this->set('metadata', json_encode($metadata));
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $metadata
   *   Metadata to merge.
   */
  public function mergeMetadata(array $metadata): self {
    $merged = array_merge($this->getMetadata(), $metadata);
    $this->set('metadata', json_encode($merged));
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLocalFileId(): ?int {
    $target = $this->get('local_file')->target_id;
    return $target ? (int) $target : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return $this->getFilename() ?? (string) $this->id();
  }

  /**
   * Returns the available purposes for AI files.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   An array of purpose keys and their corresponding labels.
   */
  public static function getAvailablePurposes(): array {
    return [
      AiFileInterface::PURPOSE_ASSISTANTS => t('Assistants'),
      AiFileInterface::PURPOSE_BATCH => t('Batch'),
      AiFileInterface::PURPOSE_FINE_TUNE => t('Fine-tune'),
      AiFileInterface::PURPOSE_VISION => t('Vision'),
      AiFileInterface::PURPOSE_USER_DATA => t('User data'),
      AiFileInterface::PURPOSE_EVALS => t('Evals'),
      AiFileInterface::PURPOSE_RAG_STORAGE => t('RAG storage'),
      AiFileInterface::PURPOSE_OCR => t('OCR'),
    ];
  }

  /**
   * Validates that a purpose value is among the allowed list.
   *
   * @param string $purpose
   *   The purpose value to validate.
   *
   * @throws \InvalidArgumentException
   *   Thrown if purpose is not recognized.
   */
  protected function validatePurpose(string $purpose): void {
    $allowed = array_keys(self::getAvailablePurposes());
    if (!in_array($purpose, $allowed, TRUE)) {
      throw new \InvalidArgumentException(sprintf('Invalid AI File purpose "%s". Allowed: %s', $purpose, implode(', ', $allowed)));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    $purpose = $this->get('purpose')->value ?? AiFileInterface::PURPOSE_USER_DATA;
    $this->validatePurpose($purpose);
  }

}
