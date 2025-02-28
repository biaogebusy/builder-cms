<?php

namespace Drupal\workspace\Plugin\QueueWorker;

use Drupal\Component\Datetime\Time;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Error;
use Drupal\multiversion\Entity\Workspace;
use Drupal\multiversion\Workspace\WorkspaceManagerInterface;
use Drupal\replication\Entity\ReplicationLogInterface;
use Drupal\user\Entity\User;
use Drupal\workspace\Entity\Replication;
use Drupal\workspace\Event\ReplicationEvent;
use Drupal\workspace\Event\ReplicationEvents;
use Drupal\workspace\ReplicatorManager;
use Relaxed\Replicator\Exception\PeerNotReachableException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class WorkspaceReplication.
 *
 * @QueueWorker(
 *   id = "workspace_replication",
 *   title = @Translation("Queue of replications"),
 *   cron = {"time" = 600}
 * )
 */
class WorkspaceReplication extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;
  use MessengerTrait;

  /**
   * The replicator manager.
   *
   * @var \Drupal\workspace\ReplicatorManager
   */
  protected $replicatorManager;

  /**
   * Time service.
   *
   * @var \Drupal\Component\Datetime\Time
   */
  protected $time;

  /**
   * The service for safe account switching.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected $accountSwitcher;

  /**
   * State system service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private $state;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\multiversion\Workspace\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * @var string
   */
  protected $workspaceDefault;

  /**
   * The event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $replicationConfig;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('workspace.replicator_manager'),
      $container->get('datetime.time'),
      $container->get('account_switcher'),
      $container->get('state'),
      $container->get('logger.factory'),
      $container->get('entity_type.manager'),
      $container->get('workspace.manager'),
      $container->getParameter('workspace.default'),
      $container->get('event_dispatcher'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ReplicatorManager $replicator_manager,
    Time $time,
    AccountSwitcherInterface $account_switcher,
    StateInterface $state,
    LoggerChannelFactoryInterface $logger,
    EntityTypeManagerInterface $entity_type_manager,
    WorkspaceManagerInterface $workspace_manager,
    $workspace_default,
    EventDispatcherInterface $event_dispatcher,
    ConfigFactoryInterface $config_factory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->replicatorManager = $replicator_manager;
    $this->time = $time;
    $this->accountSwitcher = $account_switcher;
    $this->state = $state;
    $this->logger = $logger->get('workspace');
    $this->entityTypeManager = $entity_type_manager;
    $this->workspaceManager = $workspace_manager;
    $this->workspaceDefault = $workspace_default;
    $this->eventDispatcher = $event_dispatcher;
    $this->replicationConfig = $config_factory->get('replication.settings');
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function processItem($data) {
    if ($this->state->get('workspace.last_replication_failed', FALSE)) {
      throw new SuspendQueueException('Replication is blocked!');
    }

    /** @var \Drupal\workspace\Entity\Replication $replication */
    $replication = $data['replication'];

    if ($replication_new = $this->entityTypeManager->getStorage('replication')->load($replication->id())) {
      $replication = $replication_new;
    }

    $replication_status = $replication->getReplicationStatus();
    if ($replication_status == Replication::QUEUED) {
      $account = User::load(1);
      $this->accountSwitcher->switchTo($account);

      $replication->setReplicationStatusReplicating()->save();
      $this->logger->info('Replication "@replication" has started.', ['@replication' => $replication->label()]);

      $this->eventDispatcher->dispatch(ReplicationEvents::PRE_REPLICATION, new ReplicationEvent($replication));

      $response = FALSE;
      try {
        $response = $this->replicatorManager->doReplication($replication, $data['task']);
      }
      catch (PeerNotReachableException $e) {
        $this->logger->error('The deployment could not start. Reason: ' . $e->getMessage());
        $replication
          ->setReplicationFailInfo($e->getMessage())
          ->setReplicationStatusQueued()
          ->save();
        throw new SuspendQueueException('Peer not reachable. Reason: ' . $e->getMessage());
      }
      catch (\Exception $e) {
        // When exception is thrown during replication process we want
        // replication to be marked as failed and removed from queue.
        $this->logger->error('%type: @message in %function (line %line of %file).', $variables = Error::decodeException($e));
        $replication
          ->setReplicationFailInfo($e->getMessage())
          ->setArchiveSource(FALSE)
          ->save();
      }

      if (($response instanceof ReplicationLogInterface) && ($response->get('ok')->value == TRUE)) {
        $default_workspace = Workspace::load($this->workspaceDefault);
        if ($replication->getArchiveSource() && !empty($replication->get('source')->entity->getWorkspace())) {
          $source_workspace = $replication->get('source')->entity->getWorkspace();
          $source_workspace->setUnpublished()->save();
          if ($source_workspace->id() != $this->workspaceDefault) {
            $this->workspaceManager->setActiveWorkspace($default_workspace);
            $this->messenger()->addMessage($this->t('Workspace %workspace has been archived and workspace %default has been set as active.',
              [
                '%workspace' => $replication->get('source')->entity->label(),
                '%default' => $default_workspace->label(),
              ]
            ));
          }
          else {
            $this->messenger()->addMessage($this->t('Workspace %workspace has been archived.',
              [
                '%workspace' => $replication->get('source')->entity->label(),
              ]
            ));
          }
        }
        $replication->setReplicationStatusReplicated();
        $replication->set('replicated', $this->time->getRequestTime());
        $replication->save();
        $this->logger->info('Replication "@replication" has finished successfully.', ['@replication' => $replication->label()]);
      }
      else {
        if (($response instanceof ReplicationLogInterface) && !empty($response->history->fail_info)) {
          $replication->setReplicationFailInfo($response->history->fail_info);
        }
        $replication
          ->setReplicationStatusFailed()
          ->set('replicated', $this->time->getRequestTime())
          ->setArchiveSource(FALSE)
          ->save();
        $this->state->set('workspace.last_replication_failed', TRUE);
        $this->logger->info('Replication "@replication" has failed.', ['@replication' => $replication->label()]);
      }

      $this->eventDispatcher->dispatch(ReplicationEvents::POST_REPLICATION, new ReplicationEvent($replication));

      $this->accountSwitcher->switchBack();
    }
    elseif ($replication_status == Replication::FAILED) {
      // If the replication has been marked as failed before it started to be
      // processed, do nothing, the item will just be removed from the queue.
    }
    elseif ($replication_status == Replication::REPLICATING) {
      $limit = $this->replicationConfig->get('replication_execution_limit');
      $limit = $limit ?: 1;
      $request_time = $this->time->getRequestTime();
      if ($request_time - $replication->getChangedTime() > 60 * 60 * $limit) {
        $replication
          ->setReplicationFailInfo($this->t('Replication "@replication" took too much time', ['@replication' => $replication->label()]))
          ->setReplicationStatusFailed()
          ->set('replicated', $this->time->getRequestTime())
          ->setArchiveSource(FALSE)
          ->save();
        $this->state->set('workspace.last_replication_failed', TRUE);
        $this->logger->info('Replication "@replication" exceeded the running time of @limit hours, because of that it is considered as FAILED.', ['@replication' => $replication->label(), '@limit' => $limit]);
      }
      else {
        // Log this only when the verbose logging is enabled because in some
        // rare cases a replication can fail in a way when we can't handle to
        // set the correct failed status, but it will stay in the queue as in
        // progress until it exceeds the replication_execution_limit limit.
        // This will avoid spamming watchdog with lots of replication in
        // progress messages when they are not wanted.
        if ($this->replicationConfig->get('verbose_logging')) {
          $this->logger->info('Replication "@replication" is already in progress.', ['@replication' => $replication->label()]);
        }
        throw new SuspendQueueException('Replication is already in progress!');
      }
    }
  }

}
