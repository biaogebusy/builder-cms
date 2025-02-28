<?php

namespace Drupal\replication;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\multiversion\Entity\Index\EntityIndexInterface;
use Drupal\multiversion\Entity\WorkspaceInterface;
use Drupal\multiversion\MultiversionManagerInterface;
use Drupal\replication\AllDocs\AllDocs;
use Symfony\Component\Serializer\SerializerInterface;

class AllDocsFactory implements BulkDocsFactoryInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;


  /**
   * @var \Drupal\replication\BulkDocs\BulkDocs[]
   */
  protected $instances = [];

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\multiversion\MultiversionManagerInterface $multiversion_manager
   * @param \Drupal\multiversion\Entity\Index\EntityIndexInterface $entity_index
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MultiversionManagerInterface $multiversion_manager, EntityIndexInterface $entity_index, SerializerInterface $serializer) {
    $this->entityTypeManager = $entity_type_manager;
    $this->multiversionManager = $multiversion_manager;
    $this->entityIndex = $entity_index;
    $this->serializer = $serializer;
  }

  /**
   * @inheritDoc
   */
  public function get(WorkspaceInterface $workspace) {
    if (!isset($this->instances[$workspace->id()])) {
      $this->instances[$workspace->id()] = new AllDocs(
        $this->entityTypeManager,
        $this->multiversionManager,
        $workspace,
        $this->entityIndex,
        $this->serializer
      );
    }
    return $this->instances[$workspace->id()];
  }

}
