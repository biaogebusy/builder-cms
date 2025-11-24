<?php

namespace Drupal\entity_share_client\Plugin\views\field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Views field plugin for the imported entity label and link.
 */
#[ViewsField('entity_share_client_entity_label')]
class EntityLabel extends FieldPluginBase {

  /**
   * Array of pre-loaded entities, keyed by entity type then entity ID.
   *
   * @var array
   */
  protected $loadedEntities = [];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Creates a EntityId instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);

    // Probably a dirty hack, but this plugin is only for us and don't have time
    // for rabbithole.
    $import_status_entity = $values->_entity;
    $import_status_language = $import_status_entity->language();

    $entity_type_id = $import_status_entity->entity_type_id->value;
    $imported_entity = $this->loadedEntities[$entity_type_id][$value];

    if (!$import_status_language->isLocked()) {
      $imported_entity = $imported_entity->getTranslation($import_status_language->getId());
    }

    // Allow for entity types with no URL.
    try {
      $this->options['alter']['url'] = $imported_entity->toUrl();
      $this->options['alter']['make_link'] = TRUE;
    }
    catch (UndefinedLinkTemplateException $e) {
      $this->options['alter']['make_link'] = FALSE;
    }

    return $this->sanitizeValue($imported_entity->label());
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(&$values) {
    $entity_ids_per_type = [];
    foreach ($values as $index => $row) {
      // Probably a dirty hack, but this plugin is only for us and don't have
      // time for rabbithole.
      $import_status_entity = $row->_entity;
      $entity_type_id = $import_status_entity->entity_type_id->value;

      $entity_ids_per_type[$entity_type_id][] = $this->getValue($row);
    }

    foreach ($entity_ids_per_type as $entity_type_id => $ids) {
      $this->loadedEntities[$entity_type_id] = $this->entityTypeManager->getStorage($entity_type_id)->loadMultiple($ids);
    }
  }

}
