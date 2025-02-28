<?php

namespace Drupal\menu_item_extras\Service;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionListenerInterface;
use Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormStateInterface;
use Drupal\menu_link_content\MenuLinkContentInterface;

/**
 * Class for service MenuLinkContentHelper.
 *
 * @package Drupal\menu_item_extras\Service
 */
class MenuLinkContentService implements MenuLinkContentServiceInterface {

  use DependencySerializationTrait;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * Entity definition update manager.
   *
   * @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface
   */
  private $entityDefinitionUpdateManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  private $entityFieldManager;

  /**
   * The field storage definition listener.
   *
   * @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface
   */
  private $fieldStorageDefinitionListener;

  /**
   * The entity last installed schema repository.
   *
   * @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface
   */
  private $entityLastInstalledSchemaRepository;

  /**
   * The current database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * MenuLinkContentHelper constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface $entityDefinitionUpdateManager
   *   Entity definition update manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Field\FieldStorageDefinitionListenerInterface $fieldStorageDefinitionListener
   *   The field storage definition listener.
   * @param \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $entityLastInstalledSchemaRepository
   *   The entity last installed schema repository.
   * @param \Drupal\Core\Database\Connection $connection
   *   The current database connection.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityDefinitionUpdateManagerInterface $entityDefinitionUpdateManager, EntityFieldManagerInterface $entityFieldManager, FieldStorageDefinitionListenerInterface $fieldStorageDefinitionListener, EntityLastInstalledSchemaRepositoryInterface $entityLastInstalledSchemaRepository, Connection $connection) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityDefinitionUpdateManager = $entityDefinitionUpdateManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->fieldStorageDefinitionListener = $fieldStorageDefinitionListener;
    $this->entityLastInstalledSchemaRepository = $entityLastInstalledSchemaRepository;
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public function installViewModeField() {
    $definition = $this->entityFieldManager->getFieldStorageDefinitions('menu_link_content')['view_mode'];
    $this->fieldStorageDefinitionListener->onFieldStorageDefinitionCreate($definition);
  }

  /**
   * {@inheritdoc}
   */
  public function updateMenuItemsBundle($menu_id, $extras_enabled = TRUE) {
    /** @var \Drupal\menu_link_content\MenuLinkContentInterface[] $menu_links */
    $menu_links = $this->entityTypeManager
      ->getStorage('menu_link_content')
      ->loadByProperties(['menu_name' => $menu_id]);
    if (!empty($menu_links)) {
      foreach ($menu_links as $menu_link) {
        $this->updateMenuItemBundle($menu_link, $extras_enabled);
        if ($menu_link->requiresRediscovery()) {
          $menu_link->setRequiresRediscovery(FALSE);
        }
        $menu_link->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function updateMenuItemBundle(MenuLinkContentInterface $item, $extras_enabled = TRUE, $save = FALSE) {
    $item->set(
      'bundle',
      ($extras_enabled) ? $item->getMenuName() : $item->getEntityTypeId()
    );
    if ($save) {
      $item->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function clearMenuData($menu_id = 'all') {
    // Clears view mode field in menu db table.
    $query = $this->connection->update('menu_link_content_data')
      ->fields(['view_mode' => NULL]);
    if ($menu_id !== 'all') {
      $query->condition('menu_name', $menu_id);
    }
    $query->execute();
  }

  /**
   * {@inheritdoc}
   *
   * @todo May be rewritten with states and batch for processing large data.
   */
  public function updateMenuLinkContentBundle() {
    // Retrieve existing field data.
    $tables = [
      "menu_link_content",
      "menu_link_content_data",
    ];
    $existing_data = [];
    foreach ($tables as $table) {
      // Get the old data.
      $existing_data[$table] = $this->connection->select($table)
        ->fields($table)
        ->orderBy('id', 'ASC')
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC);
      // Wipe it.
      $this->connection->truncate($table)->execute();
    }

    // Update definitions and scheme.
    // Process field storage definition changes.
    $this->entityTypeManager->clearCachedDefinitions();

    $storage_definitions = $this->entityFieldManager
      ->getFieldStorageDefinitions('menu_link_content');

    $original_storage_definitions = $this->entityLastInstalledSchemaRepository
      ->getLastInstalledFieldStorageDefinitions('menu_link_content');

    $storage_definition = $storage_definitions['bundle'] ?? NULL;

    $original_storage_definition = $original_storage_definitions['bundle'] ?? NULL;

    $this->fieldStorageDefinitionListener
      ->onFieldStorageDefinitionUpdate($storage_definition, $original_storage_definition);

    // Restore the data.
    foreach ($tables as $table) {
      if (!empty($existing_data[$table])) {
        $insert_query = $this->connection
          ->insert($table)
          ->fields(array_keys(end($existing_data[$table])));
        foreach ($existing_data[$table] as $row) {
          $insert_query->values(array_values($row));
        }
        $insert_query->execute();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function cleanupFields(ContentEntityInterface $entity) {
    foreach ($entity->getFieldDefinitions() as $field_name => $field_def) {
      if (!($field_def instanceof BaseFieldDefinition)) {
        $entity->set($field_name, NULL);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function doEntityUpdate() {
    $entity_type = $this->entityTypeManager
      ->getDefinition('menu_link_content');
    $this->entityDefinitionUpdateManager->updateEntityType($entity_type);
  }

  /**
   * Runs bundle field dtorage definition updates for menu_link_content entity.
   */
  public function doBundleFieldUpdate() {
    $entity_type = $this->entityTypeManager
      ->getDefinition('menu_link_content');
    $this->entityDefinitionUpdateManager->updateFieldStorageDefinition(
      $this->entityDefinitionUpdateManager->getFieldStorageDefinition('bundle', $entity_type->id())
    );
  }

  /**
   * Form submission handler for menu item field on the node form.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   *
   * @see menu_ui_form_node_form_submit()
   * @see menu_ui_form_node_form_alter()
   */
  public function nodeSubmit(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\EntityFormInterface $form_object */
    $form_object = $form_state->getFormObject();
    /** @var \Drupal\Core\Entity\EntityInterface $node */
    $node = $form_object->getEntity();
    if (!$form_state->isValueEmpty('menu')) {
      $values = $form_state->getValue('menu');
      if (!empty($values['enabled']) && trim($values['title'])) {
        // Decompose the selected menu parent
        // option into 'menu_name' and 'parent',
        // if the form used the default parent selection widget.
        if (!empty($values['menu_parent'])) {
          [$menu_name, $parent] = explode(':', $values['menu_parent'], 2);
          $values['menu_name'] = $menu_name;
          $values['parent'] = $parent;
        }
        $link = $this->nodeSave($node, $values);
        $values['entity_id'] = $link->id();
        $form_state->setValue('menu', $values);
      }
    }
  }

  /**
   * Helper function to create or update a menu link for a node.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   Node entity.
   * @param array $values
   *   Values for the menu link.
   *
   * @return \Drupal\menu_link_content\MenuLinkContentInterface
   *   Menu item.
   *
   * @see _menu_ui_node_save()
   */
  public function nodeSave(EntityInterface $node, array $values) {
    $menu_link_content_storage = $this->entityTypeManager
      ->getStorage('menu_link_content');
    /** @var \Drupal\menu_link_content\MenuLinkContentInterface $entity */
    if (!empty($values['entity_id'])) {
      $entity = $menu_link_content_storage->load($values['entity_id']);
      if ($entity->isTranslatable()) {
        if (!$entity->hasTranslation($node->language()->getId())) {
          $entity = $entity->addTranslation(
            $node->language()->getId(),
            $entity->toArray()
          );
        }
        else {
          $entity = $entity->getTranslation($node->language()->getId());
        }
      }
    }
    else {
      // Create a new menu_link_content entity.
      $entity = $menu_link_content_storage->create([
        'link' => ['uri' => 'entity:node/' . $node->id()],
        'langcode' => $node->language()->getId(),
      ]);
      $entity->set('enabled', 1);
    }
    $entity->set('title', trim($values['title']));
    $entity->set('description', trim($values['description']));
    $entity->set('menu_name', $values['menu_name']);
    $entity->set('bundle', $values['menu_name']);
    $entity->set('parent', $values['parent']);
    $entity->set('weight', $values['weight'] ?? 0);
    $entity->save();

    return $entity;
  }

}
