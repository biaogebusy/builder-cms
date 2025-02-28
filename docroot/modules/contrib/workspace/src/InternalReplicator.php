<?php

namespace Drupal\workspace;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Utility\Error;
use Drupal\multiversion\Entity\Index\RevisionIndexInterface;
use Drupal\multiversion\Entity\WorkspaceInterface;
use Drupal\multiversion\Workspace\WorkspaceManagerInterface;
use Drupal\replication\ChangesFactoryInterface;
use Drupal\replication\Entity\ReplicationLog;
use Drupal\replication\ReplicationTask\ReplicationTask;
use Drupal\replication\ReplicationTask\ReplicationTaskInterface;
use Drupal\replication\RevisionDiffFactoryInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * A replicator within the current Drupal runtime.
 *
 * @Replicator(
 *   id = "internal",
 *   label = "Internal Replicator"
 * )
 */
class InternalReplicator implements ReplicatorInterface {

  /**
   * @var \Drupal\multiversion\Workspace\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * @var  EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var  ChangesFactoryInterface
   */
  protected $changesFactory;

  /**
   * @var  RevisionDiffFactoryInterface
   */
  protected $revisionDiffFactory;

  /**
   * @var RevisionIndexInterface
   */
  protected $revIndex;

  /**
   * @var SerializerInterface
   */
  protected $serializer;

  /**
   * @var \Drupal\Core\Logger\LoggerChannelInterface;
   */
  protected $logger;

  /**
   * @param WorkspaceManagerInterface $workspace_manager
   * @param EntityTypeManagerInterface $entity_type_manager
   * @param ChangesFactoryInterface $changes_factory
   * @param RevisionDiffFactoryInterface $revisiondiff_factory
   * @param RevisionIndexInterface $rev_index
   * @param SerializerInterface $serializer
   * @param LoggerChannelFactoryInterface $logger;
   */
  public function __construct(WorkspaceManagerInterface $workspace_manager, EntityTypeManagerInterface $entity_type_manager, ChangesFactoryInterface $changes_factory, RevisionDiffFactoryInterface $revisiondiff_factory, RevisionIndexInterface $rev_index, SerializerInterface $serializer, LoggerChannelFactoryInterface $logger) {
    $this->workspaceManager = $workspace_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->changesFactory = $changes_factory;
    $this->revisionDiffFactory = $revisiondiff_factory;
    $this->revIndex = $rev_index;
    $this->serializer = $serializer;
    $this->logger = $logger->get('workspace');
  }

  /**
   * {@inheritdoc}
   */
  public function applies(WorkspacePointerInterface $source, WorkspacePointerInterface $target) {
    $source_workspace = $source->getWorkspace();
    $target_workspace = $target->getWorkspace();

    return ($source_workspace instanceof WorkspaceInterface) && ($target_workspace instanceof WorkspaceInterface);
  }

  /**
   * {@inheritdoc}
   */
  public function replicate(WorkspacePointerInterface $source, WorkspacePointerInterface $target, $task = NULL) {
    if ($task !== NULL && !$task instanceof ReplicationTaskInterface) {
      throw new UnexpectedTypeException($task, 'ReplicationTaskInterface');
    }
    
    $missing_found = 0;
    $docs_read = 0;
    $docs_written = 0;
    $doc_write_failures = 0;
    // Get the source and target workspaces.
    $source_workspace = $source->getWorkspace();
    $target_workspace = $target->getWorkspace();
    // Set active workspace to source.
    // @todo Avoid modifying the user's active workspace.
    $current_active = $this->workspaceManager->getActiveWorkspace();
    try {
      $this->workspaceManager->setActiveWorkspace($source_workspace);
    }
    catch (\Throwable $e) {
      $this->logger->error('%type: @message in %function (line %line of %file).', Error::decodeException($e));
      drupal_set_message($e->getMessage(), 'error');
    }
    // Fetch the site time.
    $start_time = new \DateTime();

    // If no task sent, create an empty task for its defaults.
    if ($task === NULL) {
      $task = new ReplicationTask();
    }

    $replication_log_id = $source->generateReplicationId($target, $task);
    /** @var \Drupal\replication\Entity\ReplicationLogInterface $replication_log */
    $replication_logs = \Drupal::entityTypeManager()
      ->getStorage('replication_log')
      ->loadByProperties(['uuid' => $replication_log_id]);
    $replication_log = reset($replication_logs);
    $since = 0;
    if (!empty($replication_log) && $replication_log->get('ok')->value == TRUE && $replication_log_history = $replication_log->getHistory()) {
      $dw = $replication_log_history[0]['docs_written'];
      $mf = $replication_log_history[0]['missing_found'];
      if ($dw !== NULL && $mf !== NULL && $dw == $mf) {
        $since = $replication_log->getSourceLastSeq() ?: $since;
      }
    }

    // Get changes on the source workspace.
    $parameters = $task->getParameters();
    if (!isset($parameters['doc_ids']) && $doc_ids = $task->getDocIds()) {
      $parameters['doc_ids'] = $doc_ids;
    }
    $source_changes = $this->changesFactory->get($source_workspace)
        ->filter($task->getFilter())
        ->parameters($parameters)
        ->setSince($since)
        ->getNormal();
    $data = [];
    foreach ($source_changes as $source_change) {
      $data[$source_change['id']] = [];
      foreach ($source_change['changes'] as $change) {
        $data[$source_change['id']][] = $change['rev'];
      }
    }

    // Get revisions the target workspace is missing.
    $revs_diff = $this->revisionDiffFactory->get($target_workspace)->setRevisionIds($data)->getMissing();
    while (!empty($revs_diff)) {
      $entities = [];
      $process_revs = array_splice($revs_diff, 0, 100);
      foreach ($process_revs as $uuid => $revs) {
        foreach ($revs['missing'] as $rev) {
          $missing_found++;
          $item = $this->revIndex->useWorkspace($source_workspace->id())
            ->get("$uuid:$rev");
          $entity_type_id = $item['entity_type_id'];
          $revision_id = $item['revision_id'];

          $storage = $this->entityTypeManager->getStorage($entity_type_id);
          $entity = $storage->loadRevision($revision_id);
          if ($entity instanceof ContentEntityInterface) {
            $docs_read++;
            $entities[] = $this->serializer->normalize($entity, 'json', ['new_revision_id' => TRUE]);
          }
        }
      }

      $data = [
        'new_edits' => FALSE,
        'docs' => $entities,
      ];
      // Save all entities in bulk.
      $bulk_docs = $this->serializer->denormalize($data, 'Drupal\replication\BulkDocs\BulkDocs', 'json', ['workspace' => $target_workspace]);
      $bulk_docs->save();

      foreach ($bulk_docs->getResult() as $result) {
        if (isset($result['error'])) {
          $doc_write_failures++;
        }
        elseif (!empty($result['ok'])) {
          $docs_written++;
        }
      }
    }

    $end_time = new \DateTime();
    $history = [
      'docs_read' => $docs_read,
      'docs_written' => $docs_written,
      'doc_write_failures' => $doc_write_failures,
      'missing_checked' => count($source_changes),
      'missing_found' => $missing_found,
      'start_time' => $start_time->format('D, d M Y H:i:s e'),
      'end_time' => $end_time->format('D, d M Y H:i:s e'),
      'session_id' => \md5($start_time->getTimestamp()),
      'start_last_seq' => $source_workspace->getUpdateSeq(),
    ];
    $replication_log_id = $source->generateReplicationId($target, $task);
    /** @var \Drupal\replication\Entity\ReplicationLogInterface $replication_log */
    $replication_log = ReplicationLog::loadOrCreate($replication_log_id);
    $replication_log->set('ok', TRUE);
    $replication_log->setSessionId(\md5((int) (microtime(TRUE) * 1000000)));
    $replication_log->setSourceLastSeq($source_workspace->getUpdateSeq());
    $replication_log->setHistory($history);
    $replication_log->save();

    // Switch back to the workspace that was originally active.
    $this->workspaceManager->setActiveWorkspace($current_active);

    return $replication_log;
  }

}
