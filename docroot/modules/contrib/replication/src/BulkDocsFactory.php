<?php

namespace Drupal\replication;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\multiversion\Entity\Index\RevisionIndexInterface;
use Drupal\multiversion\Entity\Index\UuidIndexInterface;
use Drupal\multiversion\Entity\WorkspaceInterface;
use Drupal\multiversion\Workspace\WorkspaceManagerInterface;
use Drupal\replication\BulkDocs\BulkDocs;

class BulkDocsFactory implements BulkDocsFactoryInterface {

  /**
   * @var \Drupal\multiversion\Workspace\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * @var \Drupal\multiversion\Entity\Index\UuidIndexInterface
   */
  protected $uuidIndex;

  /**
   * @var \Drupal\multiversion\Entity\Index\RevisionIndexInterface
   */
  protected $revIndex;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The replication settings config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Constructor.
   *
   * @param \Drupal\multiversion\Workspace\WorkspaceManagerInterface $workspace_manager
   * @param \Drupal\multiversion\Entity\Index\UuidIndexInterface $uuid_index
   * @param \Drupal\multiversion\Entity\Index\RevisionIndexInterface $rev_index
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   * @param \Drupal\Core\State\StateInterface $state
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   */
  public function __construct(WorkspaceManagerInterface $workspace_manager, UuidIndexInterface $uuid_index, RevisionIndexInterface $rev_index, EntityTypeManagerInterface $entity_type_manager, LockBackendInterface $lock, LoggerChannelFactoryInterface $logger_factory, StateInterface $state, ConfigFactoryInterface $config_factory) {
    $this->workspaceManager = $workspace_manager;
    $this->uuidIndex = $uuid_index;
    $this->revIndex = $rev_index;
    $this->entityTypeManager = $entity_type_manager;
    $this->lock = $lock;
    $this->logger = $logger_factory->get('replication');
    $this->state = $state;
    $this->config = $config_factory->get('replication.settings');
  }

  /**
   * @inheritDoc
   */
  public function get(WorkspaceInterface $workspace) {
    return new BulkDocs(
      $this->workspaceManager,
      $workspace,
      $this->uuidIndex,
      $this->revIndex,
      $this->entityTypeManager,
      $this->lock,
      $this->logger,
      $this->state,
      $this->config
    );
  }

}
