<?php

namespace Drupal\replication\AllDocs;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\multiversion\Entity\Index\EntityIndexInterface;
use Drupal\multiversion\Entity\WorkspaceInterface;
use Drupal\multiversion\MultiversionManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;

class AllDocs implements AllDocsInterface {
  use DependencySerializationTrait;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\multiversion\MultiversionManagerInterface
   */
  protected $multiversionManager;

  /**
   * @var \Drupal\multiversion\Entity\WorkspaceInterface
   */
  protected $workspace;

  /**
   * @var \Drupal\multiversion\Entity\Index\EntityIndexInterface
   */
  protected $entityIndex;

  /**
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  /**
   * @var boolean
   */
  protected $includeDocs = FALSE;

  /**
   * @var int
   */
  protected $limit = NULL;

  /**
   * @var int
   */
  protected $skip = 0;

  /**
   * @var boolean
   */
  protected $descending = FALSE;

  /**
   * @var string
   */
  protected $startKey;

  /**
   * @var string
   */
  protected $endKey;

  /**
   * @var boolean
   */
  protected $inclusiveEnd = TRUE;

  /**
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\multiversion\MultiversionManagerInterface $multiversion_manager
   * @param \Drupal\multiversion\Entity\WorkspaceInterface $workspace
   * @param \Drupal\multiversion\Entity\Index\EntityIndexInterface
   * @param \Symfony\Component\Serializer\SerializerInterface
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MultiversionManagerInterface $multiversion_manager, WorkspaceInterface $workspace, EntityIndexInterface $entity_index, SerializerInterface $serializer) {
    $this->entityTypeManager = $entity_type_manager;
    $this->multiversionManager = $multiversion_manager;
    $this->workspace = $workspace;
    $this->entityIndex = $entity_index;
    $this->serializer = $serializer;
  }

  /**
   * {@inheritdoc}
   */
  public function includeDocs($include_docs) {
    $this->includeDocs = (bool) $include_docs;
  }

  /**
   * {@inheritdoc}
   */
  public function limit($limit) {
    $this->limit = $limit;
  }

  /**
   * {@inheritdoc}
   */
  public function skip($skip) {
    $this->skip = $skip;
  }

  /**
   * {@inheritdoc}
   */
  public function descending($descending) {
    $this->descending = $descending;
  }

  /**
   * {@inheritdoc}
   */
  public function startKey($key) {
    $this->startKey = $key;
  }

  /**
   * {@inheritdoc}
   */
  public function endKey($key) {
    $this->endKey = $key;
  }

  /**
   * {@inheritdoc}
   */
  public function inclusiveEnd($inclusive_end) {
    $this->inclusiveEnd = $inclusive_end;
  }

  /**
   * {@inheritdoc}
   *
   * @todo {@link https://www.drupal.org/node/2599900 Move any logic around 'includeDocs' and the serialization format
   *   into the serializer to better separate concerns.}
   */
  public function execute() {
    $rows = [];

    $entity_types = $this->entityTypeManager->getDefinitions();
    foreach ($entity_types as $entity_type_id => $entity_type) {
      if ($this->multiversionManager->isEnabledEntityType($entity_type) && !$entity_type->get('local')) {
        try {
          $query = $this->entityTypeManager
            ->getStorage($entity_type_id)
            ->getQuery();
          if ($entity_type->get('workspace') !== FALSE) {
            $query->condition('workspace', $this->workspace->id());
          }
          $ids = $query->execute();
        }
        catch (\Exception $e) {
          watchdog_exception('replication', $e);
          continue;
        }

        $keys = [];
        foreach ($ids as $id) {
          $keys[] = $entity_type_id . ':' . $id;
        }
        $items = $this->entityIndex->getMultiple($keys);
        foreach ($items as $item) {
          if ($item['is_stub'] == TRUE) {
            continue;
          }
          $rows[$item['uuid']] = ['rev' => $item['rev']];
        }

        if ($this->includeDocs) {
          $entities = $this->entityTypeManager->getStorage($entity_type_id)->loadMultiple($ids);
          foreach ($entities as $entity) {
            if ($entity->_rev->is_stub) {
              continue;
            }
            $rows[$entity->uuid()]['doc'] = $this->serializer->normalize($entity, 'json');
          }
        }
      }
    }

    return $rows;
  }

}
