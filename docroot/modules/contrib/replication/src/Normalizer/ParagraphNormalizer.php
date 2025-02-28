<?php

namespace Drupal\replication\Normalizer;

use Drupal\Core\Entity\ContentEntityInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class ParagraphNormalizer extends ContentEntityNormalizer implements DenormalizerInterface {

  /**
   * @var string[]
   */
  protected $supportedInterfaceOrClass = ['Drupal\paragraphs\Entity\Paragraph'];

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = []) {
    $normalized = parent::normalize($entity, $format, $context);
    $langcode = $entity->language()->getId();
    $parent = $entity->getParentEntity();
    if ($parent instanceof ContentEntityInterface && !empty($normalized[$langcode]['parent_id'][0]['value'])) {
      $parent_id_field_info = [
        'entity_type_id' => $parent->getEntityTypeId(),
        'target_uuid' => $parent->uuid(),
      ];
      $bundle_key = $parent->getEntityType()->getKey('bundle');
      $bundle = $parent->bundle();
      if ($bundle_key && $bundle) {
        $parent_id_field_info[$bundle_key] = $bundle;
      }
      $normalized[$langcode]['parent_id'][0]['value'] = $parent_id_field_info;
    }
    return $normalized;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    if (!empty($data['@context']['@language'])) {
      $language = $data['@context']['@language'];
    }
    if (!empty($data[$language]['parent_id'][0]['value']['entity_type_id'])
      && !empty($data[$language]['parent_id'][0]['value']['target_uuid'])) {
      $entity_type_id = $data[$language]['parent_id'][0]['value']['entity_type_id'];
      $target_uuid = $data[$language]['parent_id'][0]['value']['target_uuid'];
      $storage = $this->entityTypeManager->getStorage($entity_type_id);
      if (!empty($context['workspace'])) {
        $storage->useWorkspace($context['workspace']->id());
      }
      $parents = $storage->loadByProperties(['uuid' => $target_uuid]);
      $parent = reset($parents);
      if ($parent instanceof ContentEntityInterface && $parent->id()) {
        $data[$language]['parent_id'][0]['value'] = $parent->id();
      }
      elseif (!$parent) {
        // Create a new entity as stub.
        $selection_instance = $this->selectionManager->getInstance(['target_type' => $entity_type_id]);
        $bundle_key = $storage->getEntityType()->getKey('bundle');
        $bundle = $entity_type_id;
        if (!empty($data[$language]['parent_id'][0]['value'][$bundle_key])) {
          $bundle = $data[$language]['parent_id'][0]['value'][$bundle_key];
        }
        $parent = $selection_instance->createNewEntity($entity_type_id, $bundle, rand(), 1);
        // Indicate that this revision is a stub.
        $parent->_rev->is_stub = TRUE;
        $parent->langcode->value = $language;
        $parent->uuid->value = $target_uuid;
        if (!empty($context['workspace'])) {
          $parent->workspace->entity = $context['workspace'];
        }
        $parent->save();
        $data[$language]['parent_id'][0]['value'] = $parent->id();
      }
      $storage->useWorkspace(NULL);
    }
    return parent::denormalize($data, $class, $format, $context);
  }

  public function supportsDenormalization($data, $type, $format = NULL) {
    if (in_array($type, ['Drupal\paragraphs\Entity\Paragraph', 'Drupal\Core\Entity\ContentEntityInterface'])) {
      if (isset($data['@type']) && $data['@type'] == 'paragraph') {
        return TRUE;
      }
    }
    return FALSE;
  }

}
