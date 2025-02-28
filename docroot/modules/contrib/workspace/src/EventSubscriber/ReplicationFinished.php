<?php

namespace Drupal\workspace\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\multiversion\Entity\Storage\ContentEntityStorageInterface;
use Drupal\multiversion\Workspace\WorkspaceManagerInterface;
use Drupal\pathauto\AliasTypeManager;
use Drupal\pathauto\PathautoGeneratorInterface;
use Drupal\workspace\Entity\Replication;
use Drupal\workspace\Event\ReplicationEvent;
use Drupal\workspace\Event\ReplicationEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class ReplicationFinished.
 *
 * @package Drupal\workspace\EventSubscriber
 */
class ReplicationFinished implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The AliasType manager.
   *
   * @var \Drupal\pathauto\AliasTypeManager
   */
  protected $aliasTypeManager;

  /**
   * The Pathauto generator.
   *
   * @var \Drupal\pathauto\PathautoGeneratorInterface
   */
  protected $pathAutoGenerator;

  /**
   * The Workspace manager.
   *
   * @var \Drupal\multiversion\Workspace\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * ReplicationFinished constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\multiversion\Workspace\WorkspaceManagerInterface $workspace_manager
   *   The workspace manager.
   * @param \Drupal\pathauto\AliasTypeManager $alias_type_manager
   *   The AliasType manager.
   * @param \Drupal\pathauto\PathautoGeneratorInterface $path_auto_generator
   *   The Pathauto generator.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, WorkspaceManagerInterface $workspace_manager, AliasTypeManager $alias_type_manager = NULL, PathautoGeneratorInterface $path_auto_generator = NULL) {
    $this->entityTypeManager = $entity_type_manager;
    $this->workspaceManager = $workspace_manager;
    $this->aliasTypeManager = $alias_type_manager;
    $this->pathAutoGenerator = $path_auto_generator;
  }

  /**
   * Listener for replication finished.
   *
   * @param \Drupal\workspace\Event\ReplicationEvent $event
   *   Drupal\workspace\Event\WorkspaceReplicationFinishedEvent.
   */
  public function onInternalReplicationFinished(ReplicationEvent $event) {
    $replication = $event->getReplication();
    if ($replication->getReplicationStatus() == Replication::REPLICATED && $this->aliasTypeManager && $this->pathAutoGenerator) {
      $source_workspace_pointer = $replication->get('source')->entity;
      $target_workspace_pointer = $replication->get('target')->entity;

      // Filtered events. If the event is triggered by a pull from external
      // source, we use the onExternalPullReplicationFinished method.
      // If the target workspace is on external sources
      // we do not handle the event.
      if ((!$source_workspace_pointer->hasField('remote_pointer') && !$source_workspace_pointer->hasField('remote_database') && !$target_workspace_pointer->hasField('remote_pointer') && !$target_workspace_pointer->hasField('remote_database'))
        || (empty($source_workspace_pointer->get('remote_pointer')->target_id) && empty($source_workspace_pointer->get('remote_database')->value) && empty($target_workspace_pointer->get('remote_pointer')->target_id) && empty($target_workspace_pointer->get('remote_database')->value))) {
        $target_workspace = $target_workspace_pointer->getWorkspace();
        $current_workspace = $this->workspaceManager->getActiveWorkspace();

        $this->workspaceManager->setActiveWorkspace($target_workspace);

        $definitions = $this->aliasTypeManager->getVisibleDefinitions();
        foreach ($definitions as $definition) {
          foreach ($definition['context'] as $entity_type => $context_definition) {
            $this->updateEntitiesAlias($entity_type);
          }
        }

        $this->workspaceManager->setActiveWorkspace($current_workspace);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ReplicationEvents::POST_REPLICATION][] = ['onInternalReplicationFinished'];
    return $events;
  }

  /**
   * {@inheritdoc}
   */
  private function updateEntitiesAlias($entity_type_id, $limit = 50) {
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    if ($storage instanceof ContentEntityStorageInterface) {
      $query = $storage->getQuery()
        ->condition('_deleted', 0);
      if ($entity_type_id === 'node') {
        $query->condition('status', 1);
      }
      $ids = $query->execute();

      foreach (array_chunk($ids, $limit) as $ids_subset) {
        $entities = $storage->loadMultiple($ids_subset);
        foreach ($entities as $entity) {
          // Update aliases for the entity's default language
          // and its translations.
          foreach ($entity->getTranslationLanguages() as $langcode => $language) {
            $translated_entity = $entity->getTranslation($langcode);
            if ($this->pathAutoGenerator->updateEntityAlias($translated_entity, 'insert')) {
              \Drupal::logger('replication')->info('Entity %entity_type(%id) alias update', ['%entity_type' => $entity_type_id, '%id' => $entity->id()]);
            }
          }
        }
      }
    }
  }

}
