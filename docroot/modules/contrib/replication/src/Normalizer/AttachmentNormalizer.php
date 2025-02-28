<?php

namespace Drupal\replication\Normalizer;

use Drupal\file\FileInterface;
use Drupal\multiversion\Entity\Index\UuidIndexInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class AttachmentNormalizer extends ContentEntityNormalizer implements DenormalizerInterface {

  /**
   * @var string[]
   */
  protected $supportedInterfaceOrClass = ['\Drupal\file\FileInterface'];

  /**
   * @var string[]
   */
  protected $format = ['stream', 'base64_stream'];

  /**
   * {@inheritdoc}
   */
  public function normalize($data, $format = NULL, array $context = []) {
    // If the 'new_revision_id' context is TRUE then normalize file entity as a
    // content entity not stream.
    if (!empty($context['new_revision_id'])) {
      return parent::normalize($data, $format, $context);
    }
    /** @var \Drupal\file\FileInterface $data */
    $stream = fopen($data->getFileUri(), 'r');
    return $stream;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    $meta_data = is_resource($data) ? stream_get_meta_data($data) : NULL;
    // @todo {@link https://www.drupal.org/node/2599926 Use $class to instantiate the entity.}
    $file_data = [];
    if (isset($meta_data['uri'])) {
      $file_data['uri'] = $meta_data['uri'];
    }
    elseif (isset($context['uri'])) {
      $file_data['uri'] = $context['uri'];
    }

    $file_info_keys = ['uuid', 'status', 'uid', 'workspace'];
    foreach ($file_info_keys as $key) {
      if (isset($context[$key])) {
        $file_data[$key] = $context[$key];
      }
    }
    if (isset($context['uuid'])) {
      $workspace = isset($context['workspace']) ? $context['workspace'] : NULL;
      /** @var UuidIndexInterface $uuid_index */
      $uuid_index = $this->indexFactory->get('multiversion.entity_index.uuid', $workspace);
      $entity_info = $uuid_index->get($context['uuid']);
      if (!empty($entity_info)) {
        /** @var FileInterface $file */
        $file = $this->entityTypeManager->getStorage($entity_info['entity_type_id'])
          ->load($entity_info['entity_id']);
        if (!empty($file)) {
          foreach ($file_data as $key => $data) {
            $file->{$key} = $data;
          }
          return $file;
        }
      }
    }
    return $this->entityTypeManager->getStorage('file')->create($file_data);
  }

}
