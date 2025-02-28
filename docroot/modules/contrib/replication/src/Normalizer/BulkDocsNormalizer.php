<?php

namespace Drupal\replication\Normalizer;

use Drupal\Component\Serialization\Json;
use Drupal\serialization\Normalizer\NormalizerBase;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class BulkDocsNormalizer extends NormalizerBase implements DenormalizerInterface {

  protected $supportedInterfaceOrClass = ['Drupal\replication\BulkDocs\BulkDocsInterface'];

  /**
   * {@inheritdoc}
   */
  public function normalize($bulk_docs, $format = NULL, array $context = []) {
    $data = [];
    /** @var \Drupal\replication\BulkDocs\BulkDocsInterface $bulk_docs */
    foreach ($bulk_docs->getResult() as $result) {
      $data[] = $result;
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    if (!isset($context['workspace'])) {
      throw new LogicException('A \'workspace\' context is required to denormalize revision diff data.');
    }

    try {
      /** @var \Drupal\replication\BulkDocs\BulkDocsInterface $bulk_docs */
      $bulk_docs = \Drupal::service('replication.bulkdocs_factory')->get($context['workspace']);

      if (
        (isset($data['new_edits']) && ($data['new_edits']) === FALSE) ||
        (isset($context['query']['new_edits']) && ($context['query']['new_edits']) === FALSE)
      ) {
        $bulk_docs->newEdits(FALSE);
      }

      $entities = [];
      if (isset($data['docs'])) {
        foreach ($data['docs'] as $doc) {
          if (!empty($doc)) {
            if (is_string($doc)) {
              $doc = Json::decode($doc);
            }
            // @todo {@link https://www.drupal.org/node/2599934 Find a more generic way to denormalize.}
            if (!empty($doc['_id']) && strpos($doc['_id'], 'local') !== FALSE) {
              // Denormalize replication_log entities. This is used when the
              // replication_log entity format is not standard, for example when
              // replicating content from PouchDB.
              list($prefix, $entity_uuid) = explode('/', $doc['_id']);
              if ($prefix == '_local' && $entity_uuid) {
                $entity = $this->serializer->denormalize($doc, 'Drupal\replication\Entity\ReplicationLog', $format, $context);
              }
            }
            // Check if the document is a valid Relaxed format.
            elseif (isset($doc['@context'])) {
              $entity = $this->serializer->denormalize($doc, 'Drupal\Core\Entity\ContentEntityInterface', $format, $context);
            }
            // Ensure a deleted doc really is marked as deleted. This may be
            // necessary when an entity is to be deleted only on certain
            // replication targets; e.g., due to filtered replication.
            if (!empty($doc['deleted']) && !empty($entity)) {
              $entity->_deleted->value = TRUE;
            }
            if (!empty($entity)) {
              $entities[] = $entity;
            }
          }
        }
      }
      $bulk_docs->setEntities($entities);
    }
    catch (\Exception $e) {
      watchdog_exception('Replication', $e);
    }

    return $bulk_docs;
  }

}
