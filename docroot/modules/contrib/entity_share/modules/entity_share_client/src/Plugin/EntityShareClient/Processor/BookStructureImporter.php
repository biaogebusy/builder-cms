<?php

declare(strict_types=1);

namespace Drupal\entity_share_client\Plugin\EntityShareClient\Processor;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\entity_share_client\RuntimeImportContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Import book structure.
 *
 * @ImportProcessor(
 *   id = "book_structure_importer",
 *   label = @Translation("Book structure"),
 *   description = @Translation("Recognizes the optional book structure of nodes and stores that locally with correcting mapping to their new node IDs. Requires JSON:API Book module enabled on the server website."),
 *   stages = {
 *     "prepare_importable_entity_data" = 20,
 *     "post_entity_save" = 20,
 *   },
 *   locked = false,
 * )
 */
class BookStructureImporter extends EntityReference {

  /**
   * The book structure entries that are entity references.
   */
  private const REFERENCES = [
    'nid',
    'bid',
    'pid',
    'p1',
    'p2',
    'p3',
    'p4',
    'p5',
    'p6',
    'p7',
    'p8',
    'p9',
  ];

  /**
   * Map from UUIDs to local node IDs.
   *
   * @var array
   */
  protected array $idMap = [];

  /**
   * The database connection from the Drupal container.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected EntityRepositoryInterface $entityRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->database = $container->get('database');
    $instance->entityRepository = $container->get('entity.repository');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareImportableEntityData(RuntimeImportContext $runtime_import_context, array &$entity_json_data): void {
    // Parse entity data to extract book structure and links to other nodes.
    // And remove this info.
    if (isset($entity_json_data['attributes']['drupal_internal__book']) && is_array($entity_json_data['attributes']['drupal_internal__book'])) {
      $book = $entity_json_data['attributes']['drupal_internal__book'];
      unset($entity_json_data['attributes']['drupal_internal__book']);

      if ($book['pid'] !== '0') {
        $entity_uuid = $book['pid__uuid'];
        // As a book page has only one parent and limited depth, no need to take
        // max recursion into account.
        if (!$runtime_import_context->isEntityMarkedForImport($entity_uuid)) {
          $runtime_import_context->addEntityMarkedForImport($entity_uuid);
          $this->importUrl($runtime_import_context, $book['pid__url']);
        }
      }

      $runtime_import_context->setBook($book['nid__uuid'], $book);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postEntitySave(RuntimeImportContext $runtime_import_context, ContentEntityInterface $processed_entity): void {
    $book = $runtime_import_context->getBook($processed_entity->uuid());
    if (empty($book)) {
      return;
    }

    foreach (self::REFERENCES as $reference) {
      if ($book[$reference] !== '0') {
        $uuid = $book[$reference . '__uuid'];
        unset($book[$reference . '__uuid'], $book[$reference . '__url']);
        if (!isset($this->idMap[$uuid])) {
          try {
            $node = $this->entityRepository->loadEntityByUuid('node', $uuid);
          }
          catch (EntityStorageException $e) {
            return;
          }
          if (!$node) {
            // The referenced node doesn't exist yet, we can't save the book
            // structure record.
            return;
          }
          $this->idMap[$uuid] = $node->id();
        }
        $book[$reference] = $this->idMap[$uuid];
      }
    }
    $this->database->merge('book')
      ->key('nid', $book['nid'])
      ->fields($book)
      ->execute();
  }

}
