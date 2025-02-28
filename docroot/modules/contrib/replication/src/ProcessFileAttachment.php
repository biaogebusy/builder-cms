<?php

namespace Drupal\replication;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\file\FileInterface;
use Drupal\multiversion\Entity\Index\MultiversionIndexFactory;
use Drupal\multiversion\Entity\WorkspaceInterface;

class ProcessFileAttachment {

  /** @var \Drupal\Core\Session\AccountProxyInterface  */
  protected $current_user;

  /** @var  \Drupal\Core\Entity\EntityTypeManagerInterface */
  protected $entity_type_manager;

  /** @var \Drupal\multiversion\Entity\Index\MultiversionIndexFactory  */
  protected $index_factory;

  function __construct(AccountProxyInterface $current_user, EntityTypeManagerInterface $entity_type_manager, MultiversionIndexFactory $index_factory) {
    $this->current_user = $current_user;
    $this->entity_type_manager = $entity_type_manager;
    $this->index_factory = $index_factory;
  }

  /**
   * Processes a file attachment.
   *
   * Returns the file object or NULL if it can't be created.
   *
   * @param array $data
   * @param string $format
   * @param \Drupal\multiversion\Entity\WorkspaceInterface $workspace
   *
   * @return \Drupal\file\FileInterface|NULL
   */
  public function process($data, $format, WorkspaceInterface $workspace = null) {
    $current_user_id = $this->current_user->id();
    $uri = $data['uri'];
    $file_uuid = $data['uuid'];
    multiversion_prepare_file_destination($uri);
    // Check if exists a file entity with this uuid.
    $uuid_index = $this->index_factory->get('multiversion.entity_index.uuid', $workspace);
    $entity_info = $uuid_index->get($file_uuid);
    if (!empty($entity_info)) {
      /** @var FileInterface $file */
      $file = $this->entity_type_manager->getStorage($entity_info['entity_type_id'])
        ->load($entity_info['entity_id']);
      if (!$file || !is_file($file->getFileUri())) {
        $file_context = [
          'uri' => $uri,
          'uuid' => $file_uuid,
          'status' => FILE_STATUS_PERMANENT,
          'uid' => $current_user_id,
        ];
        if ($workspace) {
          $file_context['workspace'] = $workspace;
        }
        $file = \Drupal::getContainer()
          ->get('serializer')
          ->deserialize($data['data'], '\Drupal\file\FileInterface', $format, $file_context);
      }
      return $file;
    }

    // Create the new entity file and the file itself.
    // Check if exists a file with this $uri, if it exists then rename the file.
    $existing_files = $this->entity_type_manager
      ->getStorage('file')
      ->loadByProperties(['uri' => $uri]);
    if (count($existing_files)) {
      $uri = file_destination($uri, FILE_EXISTS_RENAME);
    }
    $file_context = [
      'uri' => $uri,
      'uuid' => $file_uuid,
      'status' => FILE_STATUS_PERMANENT,
      'uid' => $current_user_id,
      'workspace' => $workspace,
    ];
    $file = \Drupal::getContainer()
      ->get('serializer')
      ->deserialize($data['data'], '\Drupal\file\FileInterface', $format, $file_context);

    return $file;
  }

}
