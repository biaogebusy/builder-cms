<?php

namespace Drupal\workspace\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\multiversion\Entity\Workspace;
use Drupal\multiversion\Entity\WorkspaceType;
use Drupal\multiversion\Entity\WorkspaceTypeInterface;
use Drupal\workspace\Controller\Component\ConflictListBuilder;
use Drupal\workspace\Entity\WorkspacePointer;

/**
 * WorkspaceController class.
 */
class WorkspaceController extends ControllerBase {

  /**
   * Property definition.
   *
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  protected $workspaceSettings;

  /**
   * {@inheritdoc}
   */
  public function __construct() {
    $this->workspaceSettings = \Drupal::configFactory()->getEditable('workspace.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function add() {
    $types = WorkspaceType::loadMultiple();
    if ($types && count($types) == 1) {
      $type = reset($types);
      return $this->addForm($type);
    }
    if (count($types) === 0) {
      return [
        '#markup' => $this->t('You have not created any Workspace types yet. Go to the <a href=":url">Workspace type creation page</a> to add a new Workspace type.', [
          ':url' => Url::fromRoute('entity.workspace_type.add')->toString(),
        ]),
      ];
    }

    return ['#theme' => 'workspace_add_list', '#content' => $types];

  }

  /**
   * {@inheritdoc}
   */
  public function addForm(WorkspaceTypeInterface $workspace_type) {
    $upstream_id = $this->workspaceSettings->get('upstream');
    if (!$upstream_id) {
      $upstream_id = $this->getDefaultWorkspacePointer()->id();
    }
    $workspace = Workspace::create([
      'type' => $workspace_type->id(),
      'upstream' => $upstream_id,
      'pull_replication_settings' => $this->workspaceSettings->get('pull_replication_settings', ''),
      'push_replication_settings' => $this->workspaceSettings->get('push_replication_settings', ''),
    ]);
    return $this->entityFormBuilder()->getForm($workspace);
  }

  /**
   * {@inheritdoc}
   */
  public function getAddFormTitle(WorkspaceTypeInterface $workspace_type) {
    return $this->t('Add %type workspace', ['%type' => $workspace_type->label()]);
  }

  /**
   * View a list of conflicts for a workspace.
   *
   * @param string $workspace
   *   The workspace ID to get conflicts for.
   *
   * @return array
   *   The render array to display for the page.
   */
  public function viewConflicts($workspace) {
    $container = \Drupal::getContainer();
    $builder = ConflictListBuilder::createInstance($container);
    return $builder->buildList($workspace);
  }

  /**
   * Get the page title for the list of conflicts page.
   *
   * @return string
   *   The page title.
   */
  public function getViewConflictsTitle() {
    return 'Workspace Conflicts';
  }

  /**
   * Returns the upstream for the given workspace.
   */
  protected function getDefaultWorkspacePointer() {
    $workspace_id = $this->getDefaultWorkspaceId();
    $workspace = Workspace::load($workspace_id);
    return WorkspacePointer::loadFromWorkspace($workspace);
  }

  /**
   * {@inheritdoc}
   */
  public static function getDefaultWorkspaceId() {
    return \Drupal::getContainer()->getParameter('workspace.default');
  }

}
