<?php

namespace Drupal\replication\Normalizer;

use Drupal\multiversion\Entity\WorkspaceInterface;

class CropNormalizer extends ContentEntityNormalizer {

  /**
   * @var string[]
   */
  protected $supportedInterfaceOrClass = ['\Drupal\crop\Entity\Crop'];

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = []) {
    $data = parent::normalize($entity, $format, $context);

    $cropped_entity_type_id = $entity->entity_type->value;
    $cropped_entity_storage = $this->entityTypeManager->getStorage($cropped_entity_type_id);
    $cropped_entity = $cropped_entity_storage->load($entity->entity_id->value);
    $entity_languages = $entity->getTranslationLanguages();
    foreach ($entity_languages as $entity_language) {
      // Store all needed information about entity referenced by 'entity_id' field.
      $data[$entity_language->getId()]['entity_id'][0]['crop_target_uuid'] = $cropped_entity->uuid();
      $data[$entity_language->getId()]['entity_id'][0]['crop_target_entity_type_id'] = $cropped_entity->getEntityTypeId();
      $data[$entity_language->getId()]['entity_id'][0]['crop_target_bundle'] = $cropped_entity->bundle();
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    foreach ($data as $key => &$translation) {
      if (in_array($key[0], ['_', '@'])) {
        continue;
      }
      if (!empty($translation['entity_id'][0]['crop_target_uuid'])) {
        $uuid_index = (isset($context['workspace']) && ($context['workspace'] instanceof WorkspaceInterface)) ? $this->indexFactory->get('multiversion.entity_index.uuid', $context['workspace']) : $this->indexFactory->get('multiversion.entity_index.uuid');
        if ($target_entity_info = $uuid_index->get($translation['entity_id'][0]['crop_target_uuid'])) {
          $translation['entity_id'][0]['value'] = $target_entity_info['entity_id'];
        }
        else {
          // Create a stub for entity referenced by 'entity_id' field.
          $options['target_type'] = $translation['entity_id'][0]['crop_target_entity_type_id'];
          $selection_instance = $this->selectionManager->getInstance($options);
          $target_entity = $selection_instance
            ->createNewEntity($options['target_type'], $translation['entity_id'][0]['crop_target_bundle'], rand(), 1);
          $target_entity->uri->value = "uri:stub";
          // Set the target workspace if we have it in context.
          if (isset($context['workspace'])
            && ($context['workspace'] instanceof WorkspaceInterface)
            && $target_entity->getEntityType()->get('workspace') !== FALSE
          ) {
            $target_entity->workspace->target_id = $context['workspace']->id();
          }
          $target_entity->uuid->value = $translation['entity_id'][0]['crop_target_uuid'];
          $target_entity->_rev->is_stub = TRUE;
          $target_entity->save();
          $translation['entity_id'][0]['value'] = $target_entity->id();
        }
      }
    }
    return parent::denormalize($data, $class, $format);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDenormalization($data, $type, $format = NULL) {
    if (in_array($type, [
      'Drupal\Core\Entity\ContentEntityInterface',
      'Drupal\crop\Entity\Crop'
    ], TRUE)) {
      if (!empty($data['@type']) && $data['@type'] == 'crop') {
        return TRUE;
      }
    }
    return FALSE;
  }

}
