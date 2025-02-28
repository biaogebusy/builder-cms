<?php

namespace Drupal\replication\Plugin\ReplicationFilter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\replication\Plugin\ReplicationFilter\ReplicationFilterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a filter for published entities.
 *
 * Use the configuration "include_unpublishable_entities" to determine what
 * happens to entities that do not have a "status" field, if set to TRUE they
 * will be included by the filter, else excluded.
 *
 * @ReplicationFilter(
 *   id = "published",
 *   label = @Translation("Filter Published Nodes"),
 *   description = @Translation("Replicate only nodes that are published.")
 * )
 */
class PublishedFilter extends ReplicationFilterBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager to check for "status" entity key.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new PublishedFilter.
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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'include_unpublishable_entities' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function filter(EntityInterface $entity) {
    // @todo handle translations?
    // @todo is there an easier way to tell if an entity is published?
    $definition = $this->entityTypeManager->getDefinition($entity->getEntityTypeId());
    if ($definition->hasKey('status')) {
      $field_name = $definition->getKey('status');
      $field_definition = $entity->getFieldDefinition($field_name);
      $property = $field_definition->getFieldStorageDefinition()->getMainPropertyName();
      return (bool) $entity->get($field_name)->$property;
    }
    // Determine what to do with entities without a 'status' field.
    $configuration = $this->getConfiguration();
    return $configuration['include_unpublishable_entities'];
  }

}
