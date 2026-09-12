<?php

namespace Drupal\webform_access\Hook;

use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform_access.
 */
class WebformAccessHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_webform_help_info().
   */
  #[Hook('webform_help_info')]
  public function webformHelpInfo() {
    $help = [];
    // Access group.
    $help['webform_access_group'] = [
      'group' => 'access',
      'title' => $this->t('Webform Access: Group'),
      'content' => $this->t('The <strong>Access group</strong> page lists reusable groups used to access webform source entity and users.'),
      'video_id' => 'access',
      'routes' => [
              // @see /admin/structure/webform/access/group/manage
        'entity.webform_access_group.collection',
      ],
    ];
    // Access type.
    $help['webform_access_type'] = [
      'type' => 'access',
      'title' => $this->t('Webform Access: Type'),
      'content' => $this->t('The <strong>Access type</strong> page lists types of groups used to send email notifications to users.'),
      'video_id' => 'access',
      'routes' => [
              // @see /admin/structure/webform/access/type/manage
        'entity.webform_access_type.collection',
      ],
    ];
    return $help;
  }

  /* ************************************************************************** */
  // Delete relationship hooks.
  /* ************************************************************************** */

  /**
   * Implements hook_user_delete().
   */
  #[Hook('user_delete')]
  public function userDelete(EntityInterface $entity) {
    \Drupal::database()->delete('webform_access_group_user')->condition('uid', $entity->id())->execute();
  }

  /**
   * Implements hook_node_delete().
   */
  #[Hook('node_delete')]
  public function nodeDelete(EntityInterface $entity) {
    \Drupal::database()->delete('webform_access_group_entity')->condition('entity_type', 'node')->condition('entity_id', $entity->id())->execute();
  }

  /**
   * Implements hook_field_config_delete().
   */
  #[Hook('field_config_delete')]
  public function fieldConfigDelete(EntityInterface $entity) {
    /** @var \Drupal\field\Entity\FieldConfig $definition */
    if ($entity->getType() === 'webform' && $entity->getEntityTypeId() === 'node') {
      $entity_ids = \Drupal::entityQuery('webform_access_group')->condition('type', $entity->getTargetBundle())->execute();
      if ($entity_ids) {
        \Drupal::database()->delete('webform_access_group_entity')->condition('entity_type', 'node')->condition('entity_id', $entity_ids, 'IN')->condition('field_name', $entity->getName())->execute();
      }
    }
  }

  /**
   * Implements hook_field_storage_config_delete().
   */
  #[Hook('field_storage_config_delete')]
  public function fieldStorageConfigDelete(EntityInterface $entity) {
    /** @var \Drupal\field\Entity\FieldStorageConfig $entity */
    if ($entity->getType() === 'webform') {
      \Drupal::database()->delete('webform_access_group_entity')->condition('entity_type', $entity->getEntityTypeId())->condition('field_name', $entity->getName())->execute();
    }
  }

  /* ************************************************************************** */
  // Access checking.
  /* ************************************************************************** */

  /**
   * Implements hook_menu_local_tasks_alter().
   *
   * Add webform access group to local task cacheability.
   *
   * @see \Drupal\Core\Menu\Plugin\Block\LocalTasksBlock::build
   */
  #[Hook('menu_local_tasks_alter')]
  public function menuLocalTasksAlter(&$data, $route_name) {
    // Change config entities 'Translate *' tab to be just label 'Translate'.
    $webform_entities = ['webform_access_group', 'webform_access_type'];
    foreach ($webform_entities as $webform_entity) {
      if (isset($data['tabs'][0]["config_translation.local_tasks:entity.{$webform_entity}.config_translation_overview"]['#link']['title'])) {
        $data['tabs'][0]["config_translation.local_tasks:entity.{$webform_entity}.config_translation_overview"]['#link']['title'] = $this->t('Translate');
      }
    }
    $route_name = \Drupal::routeMatch()->getRouteName();
    if ($route_name !== 'entity.node.canonical' && !str_starts_with($route_name, 'entity.node.webform.')) {
      return;
    }
    /** @var \Drupal\webform\WebformRequestInterface $request_handler */
    $request_handler = \Drupal::service('webform.request');
    $account = \Drupal::currentUser();
    $webform = $request_handler->getCurrentWebform();
    $source_entity = $request_handler->getCurrentSourceEntity();
    if (!$webform || $source_entity) {
      return;
    }
    /** @var \Drupal\webform_access\WebformAccessGroupStorageInterface $webform_access_group */
    $webform_access_group_storage = \Drupal::entityTypeManager()->getStorage('webform_access_group');
    $webform_access_groups = $webform_access_group_storage->loadByEntities($webform, $source_entity, $account);
    if (empty($webform_access_groups)) {
      return;
    }
    /** @var \Drupal\Core\Cache\CacheableMetadata $cacheability */
    $cacheability = $data['cacheability'];
    foreach ($webform_access_groups as $webform_access_group) {
      $cacheability->addCacheableDependency($webform_access_group);
    }
  }

  /**
   * Implements hook_webform_submission_query_access_alter().
   */
  #[Hook('webform_submission_query_access_alter')]
  public function webformSubmissionQueryAccessAlter(AlterableInterface $query, array $webform_submission_tables) {
    $account = $query->getMetaData('account') ?: \Drupal::currentUser();
    // Collect access group ids with 'view_any' or 'administer' permissions.
    /** @var \Drupal\webform_access\WebformAccessGroupStorageInterface $access_group_storage */
    $access_group_storage = \Drupal::entityTypeManager()->getStorage('webform_access_group');
    /** @var \Drupal\webform_access\WebformAccessGroupInterface $access_group */
    $access_groups = $access_group_storage->loadByEntities(NULL, NULL, $account);
    $access_any_group_ids = [];
    $access_own_group_ids = [];
    foreach ($access_groups as $access_group) {
      $access_group_permissions = $access_group->get('permissions');
      $access_group_permissions = array_combine($access_group_permissions, $access_group_permissions);
      if (isset($access_group_permissions['view_any']) || isset($access_group_permissions['administer'])) {
        $access_any_group_ids[] = $access_group->id();
      }
      elseif (isset($access_group_permissions['view_own'])) {
        $access_own_group_ids[] = $access_group->id();
      }
    }
    if ($access_any_group_ids) {
      // Add access group entity type, entity id, and webform id to the query.
      $result = \Drupal::database()->select('webform_access_group_entity', 'ge')->fields('ge', ['entity_type', 'entity_id', 'webform_id'])->condition('group_id', $access_any_group_ids, 'IN')->execute();
      while ($record = $result->fetchAssoc()) {
        foreach ($webform_submission_tables as $table) {
          /** @var \Drupal\Core\Database\Query\SelectInterface $query */
          $condition = $query->andConditionGroup();
          $condition->condition($table['alias'] . '.entity_type', $record['entity_type']);
          $condition->condition($table['alias'] . '.entity_id', (string) $record['entity_id']);
          $condition->condition($table['alias'] . '.webform_id', $record['webform_id']);
          $table['condition']->condition($condition);
        }
      }
    }
    if ($access_own_group_ids) {
      // Add access group entity type, entity id, and webform id to the query.
      $result = \Drupal::database()->select('webform_access_group_entity', 'ge')->fields('ge', ['entity_type', 'entity_id', 'webform_id'])->condition('group_id', $access_own_group_ids, 'IN')->execute();
      while ($record = $result->fetchAssoc()) {
        foreach ($webform_submission_tables as $table) {
          /** @var \Drupal\Core\Database\Query\SelectInterface $query */
          $condition = $query->andConditionGroup();
          $condition->condition($table['alias'] . '.uid', $account->id());
          $condition->condition($table['alias'] . '.entity_type', $record['entity_type']);
          $condition->condition($table['alias'] . '.entity_id', (string) $record['entity_id']);
          $condition->condition($table['alias'] . '.webform_id', $record['webform_id']);
          $table['condition']->condition($condition);
        }
      }
    }
  }

  /* ************************************************************************** */
  // Webform access groups (node) entity.
  /* ************************************************************************** */

  /**
   * Implements hook_field_widget_single_element_form_alter().
   */
  #[Hook('field_widget_single_element_form_alter')]
  public function fieldWidgetSingleElementFormAlter(&$element, FormStateInterface $form_state, $context) {
    /** @var \Drupal\Core\Field\FieldItemListInterface $items */
    $items = $context['items'];
    $field_definition = $items->getFieldDefinition();
    if ($field_definition->getType() !== 'webform') {
      return;
    }
    // Get the target entity.
    $entity = $items->getEntity();
    // Only nodes are currently supported.
    if ($entity->getEntityTypeId() !== 'node') {
      return;
    }
    $default_value = $entity->id() ? \Drupal::database()->select('webform_access_group_entity', 'ge')->fields('ge', ['group_id'])->condition('entity_type', $entity->getEntityTypeId())->condition('entity_id', $entity->id())->condition('webform_id', $element['target_id']['#default_value'])->condition('field_name', $field_definition->getName())->execute()->fetchCol() : ($items->webform_access_group ?: []);
    $element['settings']['webform_access_group'] = _webform_access_group_build_element($default_value, [], $form_state);
  }

}
