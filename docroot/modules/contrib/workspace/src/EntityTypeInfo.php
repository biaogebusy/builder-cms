<?php

namespace Drupal\workspace;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Url;
use Drupal\multiversion\MultiversionManagerInterface;
use Drupal\workspace\Entity\Form\WorkspaceForm;
use Drupal\workspace\Entity\Form\WorkspaceDeleteForm;
use Drupal\workspace\Entity\Form\WorkspaceTypeDeleteForm;
use Drupal\workspace\Entity\Form\WorkspaceTypeForm;

/**
 * Service class for manipulating entity type information.
 *
 * This class contains primarily bridged hooks for compile-time or
 * cache-clear-time hooks. Runtime hooks should be placed in EntityOperations.
 */
class EntityTypeInfo {

  /**
   * @var \Drupal\multiversion\MultiversionManagerInterface
   */
  protected $multiversionManager;

  /**
   * Constructs a new Toolbar.
   *
   * @param \Drupal\multiversion\MultiversionManagerInterface $multiversion_manager
   */
  public function __construct(MultiversionManagerInterface $multiversion_manager) {
    $this->multiversionManager = $multiversion_manager;
  }

  /**
   * @param array $entity_types
   */
  public function entityTypeBuild(array &$entity_types) {
    if (isset($entity_types['workspace_type'])) {
      $entity_types['workspace_type'] = $this->buildWorkspaceType($entity_types['workspace_type']);
    }

    if (isset($entity_types['workspace'])) {
      $entity_types['workspace'] = $this->buildWorkspace($entity_types['workspace']);
    }
  }

  /**
   * @param array $entity_types
   */
  public function entityTypeAlter(array &$entity_types) {
    foreach ($this->selectMultiversionedUiEntityTypes($entity_types) as $type_name => $entity_type) {
      $entity_types[$type_name] = $this->addRevisionLinks($entity_type);
    }
  }

  /**
   * Returns just those entity definitions that need multiversion UI enhancement.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface[] $entity_types
   *   The entity types to check.
   * @return EntityTypeInterface[]
   *   Just the entities that we care about.
   */
  protected function selectMultiversionedUiEntityTypes(array $entity_types) {
    return array_filter($entity_types, function (EntityTypeInterface $type) {
      return $this->multiversionManager->isEnabledEntityType($type)
      && $type->hasViewBuilderClass()
      && $type->hasLinkTemplate('canonical');
    });
  }

  /**
   * @param \Drupal\Core\Entity\EntityTypeInterface $workspace_type
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   */
  protected function buildWorkspaceType(EntityTypeInterface $workspace_type) {
    $workspace_type->setHandlerClass('list_builder', WorkspaceTypeListBuilder::class);
    $providers = $workspace_type->getRouteProviderClasses() ?: [];
    $providers['html'] = AdminHtmlRouteProvider::class;
    $workspace_type->setHandlerClass('route_provider', $providers);
    $workspace_type->setFormClass('default', WorkspaceTypeForm::class);
    $workspace_type->setFormClass('add', WorkspaceTypeForm::class);
    $workspace_type->setFormClass('edit', WorkspaceTypeForm::class);
    $workspace_type->setFormClass('delete', WorkspaceTypeDeleteForm::class);
    $workspace_type->setLinkTemplate('edit-form', '/admin/structure/workspace/types/{workspace_type}/edit');
    $workspace_type->setLinkTemplate('delete-form', '/admin/structure/workspace/types/{workspace_type}/delete');
    $workspace_type->setLinkTemplate('collection', '/admin/structure/workspace/types');

    return $workspace_type;
  }

  /**
   * @param \Drupal\Core\Entity\EntityTypeInterface $workspace
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   */
  protected function buildWorkspace(EntityTypeInterface $workspace) {
    $workspace->setHandlerClass('list_builder', WorkspaceListBuilder::class);
    $providers = $workspace->getRouteProviderClasses() ?: [];
    $providers['html'] = AdminHtmlRouteProvider::class;
    $workspace->setHandlerClass('route_provider', $providers);
    $workspace->setFormClass('default', WorkspaceForm::class);
    $workspace->setFormClass('add', WorkspaceForm::class);
    $workspace->setFormClass('edit', WorkspaceForm::class);
    $workspace->setFormClass('delete', WorkspaceDeleteForm::class);
    $workspace->setLinkTemplate('collection', '/admin/structure/workspace');
    $workspace->setLinkTemplate('canonical', '/admin/structure/workspace/{workspace}');
    $workspace->setLinkTemplate('edit-form', '/admin/structure/workspace/{workspace}/edit');
    $workspace->setLinkTemplate('delete-form', '/admin/structure/workspace/{workspace}/delete');
    $workspace->setLinkTemplate('activate-form', '/admin/structure/workspace/{workspace}/activate');
    $workspace->setLinkTemplate('unarchive-form', '/admin/structure/workspace/{workspace}/unarchive');
    $workspace->setLinkTemplate('conflicts', '/admin/structure/workspace/{workspace}/conflicts');
    $workspace->setLinkTemplate('changes', '/admin/structure/workspace/{workspace}/changes');
    $workspace->set('field_ui_base_route', 'entity.workspace_type.edit_form');

    return $workspace;
  }

  /**
   * Adds additional link relationships to an entity.
   *
   * If these links already exist they will not be overridden.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   An entity type defintion to which to add links.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   The modified type definition.
   */
  protected function addRevisionLinks(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('canonical')) {
      if (!$entity_type->hasLinkTemplate('version-tree')) {
        $entity_type->setLinkTemplate('version-tree', $entity_type->getLinkTemplate('canonical') . '/tree');
      }

      if (!$entity_type->hasLinkTemplate('revision')) {
        $entity_type->setLinkTemplate('revision', $entity_type->getLinkTemplate('canonical') . '/revisions/{' . $entity_type->id() . '_revision}/view');
      }
    }

    return $entity_type;
  }

  /**
   * Adds base field info to an entity type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   Entity type for adding base fields to.
   *
   * @return \Drupal\Core\Field\BaseFieldDefinition[]
   *   New fields added Workspace.
   */
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type) {
    if ($entity_type->id() != 'workspace') {
      return [];
    }

    $fields['upstream'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Assign default target workspace'))
      ->setDescription(t('The workspace to push to and pull from.'))
      ->setRevisionable(TRUE)
      ->setRequired(TRUE)
      ->setSetting('target_type', 'workspace_pointer')
      ->setDefaultValueCallback('workspace_active_id')
      ->setDisplayOptions('form', [
        'type' => 'options_target_workspace_select',
        'weight' => 5
      ]);

    $fields['pull_replication_settings'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Replication settings on update'))
      ->setDescription(t('The settings to use when content is pulled from upstream.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'replication_settings')
      ->setDisplayOptions('form', [
        'type' => 'options_filters_select',
        'weight' => 6
      ]);

    $fields['push_replication_settings'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Replication settings on deploy'))
      ->setDescription(t('The settings to use when content is pushed to upstream.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'replication_settings')
      ->setDisplayOptions('form', [
        'type' => 'options_filters_select',
        'weight' => 7
      ]);

    return $fields;
  }

}
