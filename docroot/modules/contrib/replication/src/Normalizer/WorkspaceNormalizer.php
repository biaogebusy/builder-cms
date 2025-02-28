<?php

namespace Drupal\replication\Normalizer;

use Drupal\multiversion\Entity\WorkspaceType;
use Drupal\multiversion\Entity\WorkspaceTypeInterface;
use Drupal\serialization\Normalizer\EntityNormalizer;

/**
 * @todo {@link https://www.drupal.org/node/2599920 Don't extend EntityNormalizer.}
 */
class WorkspaceNormalizer extends EntityNormalizer {

  /**
   * @var string[]
   */
  protected $supportedInterfaceOrClass = ['Drupal\multiversion\Entity\WorkspaceInterface'];

  /**
   * @var string[]
   */
  protected $format = ['json'];

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = []) {
    $context['entity_type'] = 'workspace';
    $data = parent::normalize($entity, $format, $context);

    $return_data = [];
    if (isset($data['machine_name'])) {
      $return_data['db_name'] = (string) $entity->getMachineName();
    }
    if ($update_seq = $entity->getUpdateSeq()) {
      $return_data['update_seq'] = (int) $update_seq;
    }
    else {
      // Replicator expects update_seq to be always set.
      $return_data['update_seq'] = 0;
    }
    if (isset($data['created'])) {
      $return_data['instance_start_time'] = (string) $entity->getStartTime();
    }

    return $return_data;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    if (isset($data['db_name'])) {
      $data['machine_name'] = $data['db_name'];
      unset($data['db_name']);
    }
    if (isset($data['instance_start_time'])) {
      $data['created'] = $data['instance_start_time'];
      unset($data['instance_start_time']);
    }
    $workspace_types = WorkspaceType::loadMultiple();
    $workspace_type = reset($workspace_types);
    if (!($workspace_type instanceof WorkspaceTypeInterface)) {
      throw new \Exception('Invalid workspace type.');
    }
    $data['type'] = $workspace_type->id();
    return \Drupal::entityTypeManager()->getStorage('workspace')->create($data);
  }
}
