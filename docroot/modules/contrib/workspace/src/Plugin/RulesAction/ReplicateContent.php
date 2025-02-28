<?php

namespace Drupal\workspace\Plugin\RulesAction;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\multiversion\Entity\WorkspaceInterface;
use Drupal\multiversion\MultiversionManagerInterface;
use Drupal\multiversion\Workspace\WorkspaceManagerInterface;
use Drupal\rules\Core\RulesActionBase;
use Drupal\workspace\ReplicatorManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Save entity' action.
 *
 * @RulesAction(
 *   id = "workspace_replicate_content",
 *   label = @Translation("Replicate content"),
 *   category = @Translation("Workspace"),
 *   context = {
 *     "entity" = @ContextDefinition("entity",
 *       label = @Translation("Entity"),
 *       description = @Translation("Specifies the entity, which the source workspace is derived from.")
 *     )
 *   }
 * )
 *
 */
class ReplicateContent extends RulesActionBase implements ContainerFactoryPluginInterface {

  /** @var  WorkspaceManagerInterface */
  protected $workspaceManager;

  /** @var  EntityTypeManagerInterface */
  protected $entityTypeManager;

  /** @var  ReplicatorManager */
  protected $replicatorManager;

  /** @var  MultiversionManagerInterface */
  protected $multiversionManager;

  /**
   * Constructs a new ReplicateContent.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\multiversion\Workspace\WorkspaceManagerInterface $workspace_manager
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\workspace\ReplicatorManager $replicator_manager
   * @param \Drupal\multiversion\MultiversionManagerInterface $multiversion_manager
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, WorkspaceManagerInterface $workspace_manager, EntityTypeManagerInterface $entity_type_manager, ReplicatorManager $replicator_manager, MultiversionManagerInterface $multiversion_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->workspaceManager = $workspace_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->replicatorManager = $replicator_manager;
    $this->multiversionManager = $multiversion_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('workspace.manager'),
      $container->get('entity_type.manager'),
      $container->get('workspace.replicator_manager'),
      $container->get('multiversion.manager')
      );
  }

  /**
   * Replicate content from active Workspace to it's upstream.
   */
  protected function doExecute(EntityInterface $entity) {
    /** @var \Drupal\multiversion\Entity\Workspace $workspace */
    $workspace = $this->multiversionManager->isSupportedEntityType($entity->getEntityType())
      ? $entity->get('workspace')->entity
      : $this->workspaceManager->getActiveWorkspace();

    $source = $this->getPointerToWorkspace($workspace);

    /** @var \Drupal\workspace\WorkspacePointerInterface $upstream */
    $upstream = $workspace->get('upstream')->entity;

    // Derive a replication task from the source Workspace.
    $task = $this->replicatorManager->getTask($workspace, 'push_replication_settings');

    /** @var \Drupal\replication\Entity\ReplicationLogInterface $result */
    $result = $this->replicatorManager->replicate($source, $upstream, $task);

    if ($result->get('ok')->value == TRUE) {
      drupal_set_message($this->t('Content replicated from workspace @source to workspace @upstream.',
        ['@source' => $workspace->label(), '@upstream' => $upstream->label()]));
    }
    else {
      drupal_set_message($this->t('Error replicating content.'));
    }
  }

  /**
   * Returns a pointer to the specified workspace.
   *
   * In most cases this pointer will be unique, but that is not guaranteed
   * by the schema. If there are multiple pointers, which one is returned is
   * undefined.
   *
   * @param \Drupal\multiversion\Entity\WorkspaceInterface $workspace
   *   The workspace for which we want a pointer.
   *
   * @return \Drupal\workspace\WorkspacePointerInterface
   *   The pointer to the provided workspace.
   */
  protected function getPointerToWorkspace(WorkspaceInterface $workspace) {
    $pointers = $this->entityTypeManager
      ->getStorage('workspace_pointer')
      ->loadByProperties(['workspace_pointer' => $workspace->id()]);
    $pointer = reset($pointers);
    return $pointer;
  }

}
