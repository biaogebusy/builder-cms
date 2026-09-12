<?php

namespace Drupal\webform_node\Hook;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform_node.
 */
class WebformNodeHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types) {
    if (isset($entity_types['webform'])) {
      /** @var \Drupal\Core\Entity\ContentEntityTypeInterface $webform_entity_type */
      $webform_entity_type = $entity_types['webform'];
      $webform_entity_type->setLinkTemplate('references', '/admin/structure/webform/manage/{webform}/references');
    }
  }

  /**
   * Implements hook_entity_operation().
   */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $entity) {
    $operations = [];
    if ($entity instanceof WebformInterface && $entity->access('update')) {
      $operations['references'] = [
        'title' => $this->t('References'),
        'url' => $entity->toUrl('references'),
        'weight' => 40,
      ];
    }
    return $operations;
  }

  /**
   * Implements hook_node_access().
   */
  #[Hook('node_access')]
  public function nodeAccess(NodeInterface $node, $operation, AccountInterface $account) {
    if (!str_starts_with($operation, 'webform_submission_')) {
      return AccessResult::neutral();
    }
    else {
      /** @var \Drupal\webform\WebformEntityReferenceManagerInterface $entity_reference_manager */
      $entity_reference_manager = \Drupal::service('webform.entity_reference_manager');
      // Check that the node has a webform field that has been populated.
      $webform = $entity_reference_manager->getWebform($node);
      if (!$webform) {
        return AccessResult::forbidden();
      }
      // Check administer webform submissions.
      if ($account->hasPermission('administer webform submission')) {
        return AccessResult::allowed();
      }
      // Change access to ANY submission.
      $operation = str_replace('webform_submission_', '', $operation);
      $any_permission = "{$operation} webform submissions any node";
      if ($account->hasPermission($any_permission)) {
        return AccessResult::allowed();
      }
      // Change access to submission associated with the node's webform.
      $own_permission = "{$operation} webform submissions own node";
      if ($account->hasPermission($own_permission) && $node->getOwnerId() === $account->id()) {
        return AccessResult::allowed();
      }
      return AccessResult::neutral();
    }
  }

  /**
   * Implements hook_webform_submission_query_access_alter().
   */
  #[Hook('webform_submission_query_access_alter')]
  public function webformSubmissionQueryAccessAlter(AlterableInterface $query, array $webform_submission_tables) {
    $route_name = \Drupal::routeMatch()->getRouteName();
    if (!preg_match('/entity\.([^.]+)\.webform\.results_submissions/', $route_name, $match)) {
      return;
    }
    $entity_type = $match[1];
    $account = $query->getMetaData('account') ?: \Drupal::currentUser();
    if ($account->hasPermission('view webform submissions any node')) {
      foreach ($webform_submission_tables as $table) {
        $table['condition']->condition($table['alias'] . '.entity_type', $entity_type);
      }
    }
    elseif ($account->hasPermission('view webform submissions own node')) {
      $entity_id = \Drupal::routeMatch()->getRawParameter($entity_type);
      foreach ($webform_submission_tables as $table) {
        /** @var \Drupal\Core\Database\Query\SelectInterface $query */
        $condition = $query->andConditionGroup();
        $condition->condition($table['alias'] . '.entity_type', $entity_type);
        $condition->condition($table['alias'] . '.entity_id', $entity_id);
        $table['condition']->condition($condition);
      }
    }
  }

  /**
   * Implements hook_node_prepare_form().
   *
   * Prepopulate a node's webform field target id.
   *
   * @see \Drupal\webform_node\Controller\WebformNodeReferencesListController::render
   */
  #[Hook('node_prepare_form')]
  public function nodePrepareForm(NodeInterface $node, $operation, FormStateInterface $form_state) {
    // Only prepopulate new nodes.
    if (!$node->isNew()) {
      return;
    }
    /** @var \Drupal\webform\WebformEntityReferenceManagerInterface $entity_reference_manager */
    $entity_reference_manager = \Drupal::service('webform.entity_reference_manager');
    // Make the node has a webform (entity reference) field.
    $field_name = $entity_reference_manager->getFieldName($node);
    if (!$field_name) {
      return;
    }
    // Populate the node's title, webform field id and default data.
    $query = \Drupal::request()->query->all();
    $webform_id = $query['webform_id'] ?? NULL;
    if ($webform_id && ($webform = Webform::load($webform_id))) {
      $node->{$field_name}->target_id = $webform_id;
      $node->title->value = $query['webform_title'] ?? $webform->label();
      $webform_default_data = $query['webform_default_data'] ?? NULL;
      if ($webform_default_data) {
        $node->{$field_name}->default_data = is_array($webform_default_data) ? Yaml::encode($webform_default_data) : $webform_default_data;
      }
    }
  }

  /**
   * Implements hook_node_delete().
   *
   * Remove user specified entity references.
   */
  #[Hook('node_delete')]
  public function nodeDelete(NodeInterface $node) {
    /** @var \Drupal\webform\WebformEntityReferenceManagerInterface $entity_reference_manager */
    $entity_reference_manager = \Drupal::service('webform.entity_reference_manager');
    $entity_reference_manager->deleteUserWebformId($node);
  }

}
