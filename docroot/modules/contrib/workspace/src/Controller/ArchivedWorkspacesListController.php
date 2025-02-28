<?php

namespace Drupal\workspace\Controller;

use Drupal\Core\Controller\ControllerBase;

class ArchivedWorkspacesListController extends ControllerBase {

  public function viewArchivedWorkspaces() {
    $archived_workspaces = $this->entityTypeManager()
      ->getStorage('workspace')
      ->loadByProperties(['published' => FALSE]);
    $headers = [
      $this->t('Workspace'),
      $this->t('Owner'),
      $this->t('Type'),
      $this->t('Status'),
      $this->t('Operations'),
    ];
    $rows = [];
    if (!empty($archived_workspaces)) {
      /** @var \Drupal\multiversion\Entity\WorkspaceInterface $entity */
      foreach ($archived_workspaces as $entity) {
        // Don't display entities queued for delete.
        if ($entity->getQueuedForDelete()) {
          continue;
        }
        $row = [];
        $row[] = $entity->label() . ' (' . $entity->getMachineName() . ')';
        $row[] = $entity->getOwner()->getDisplayname();
        $row[] = $entity->get('type')->first()->entity->label();
        // All archived workspaces are inactive.
        $row[] = $this->t('Inactive')->render();
        // Set operations.
        $links = [];
        if ($entity->hasLinkTemplate('delete-form')) {
          $links['delete'] = [
            'title' => t('Delete'),
            'url' => $entity->toUrl('delete-form', ['absolute' => TRUE]),
          ];
        }
        if ($entity->hasLinkTemplate('unarchive-form') && !$entity->isPublished()) {
          $links['Unarchive'] = [
            'title' => t('Unarchive'),
            'url' => $entity->toUrl('unarchive-form', ['absolute' => TRUE]),
          ];
        }
        $row[] = [
          'data' => [
            '#type' => 'operations',
            '#links' => $links,
          ],
        ];
        $rows[] = $row;
      }
    }

    $build['archived-workspaces-list'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => t('There are no archived workspaces.'),
    ];
    $build['pager'] = [
      '#type' => 'pager',
    ];

    return $build;
  }

}
