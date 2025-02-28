<?php

namespace Drupal\replication\Normalizer;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\FieldableEntityStorageInterface;
use Drupal\file\FileInterface;
use Drupal\serialization\Normalizer\FieldItemNormalizer;

class EntityReferenceItemNormalizer extends FieldItemNormalizer {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = 'Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem';

  /**
   * @var string[]
   */
  protected $format = ['json'];

  /**
   * {@inheritdoc}
   */
  public function normalize($field, $format = NULL, array $context = []) {
    $value = $field->getValue();
    $target_type = $field->getFieldDefinition()->getSetting('target_type');
    $storage = \Drupal::entityTypeManager()->getStorage($target_type);

    if (!($storage instanceof FieldableEntityStorageInterface)) {
      return $value;
    }

    $taget_id = isset($value['target_id']) ? $value['target_id'] : NULL;
    if ($taget_id === NULL) {
      return $value;
    }

    $referenced_entity = $storage->load($taget_id);
    if (!$referenced_entity instanceof ContentEntityInterface) {
      return $value;
    }

    $field_info = [
      'entity_type_id' => $target_type,
      'target_uuid' => $referenced_entity->uuid(),
    ];

    // Add username to the field info for user entity type.
    if ($target_type === 'user' && $username = $referenced_entity->getDisplayName()) {
      $field_info['username'] = $username;
    }

    if ($referenced_entity instanceof FileInterface) {
      $file_info = $value;
      unset($file_info['target_id']);
      $field_info += $file_info;
      $field_info['uri'] = $referenced_entity->getFileUri();
      $field_info['filename'] = $referenced_entity->getFilename();
      $field_info['filesize'] = $referenced_entity->getSize();
      $field_info['filemime'] = $referenced_entity->getMimeType();
    }

    $bundle_key = $referenced_entity->getEntityType()->getKey('bundle');
    $bundle = $referenced_entity->bundle();
    if ($bundle_key && $bundle) {
      $field_info[$bundle_key] = $bundle;
    }

    return $field_info;
  }

}
