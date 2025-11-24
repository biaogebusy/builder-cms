<?php

namespace Drupal\entity_share_client\Plugin\views\field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Views field plugin for our config entity labels.
 */
#[ViewsField('entity_share_client_config_entity_label')]
class ConfigEntityLabel extends FieldPluginBase {

  /**
   * Array of entities.
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
   * Creates a ConfigEntityLabel instance.
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
    $type = $this->definition['entity_type_id'];
    $value = $this->getValue($values);

    if (empty($this->loadedEntities[$type][$value])) {
      return;
    }

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $this->loadedEntities[$type][$value];

    $this->options['alter']['url'] = $entity->toUrl('edit-form');
    $this->options['alter']['make_link'] = TRUE;

    return $this->sanitizeValue($entity->label());
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(&$values) {
    $entity_ids_per_type = [];
    foreach ($values as $value) {
      $type = $this->definition['entity_type_id'];
      $entity_ids_per_type[$type][] = $this->getValue($value);
    }

    foreach ($entity_ids_per_type as $type => $ids) {
      $this->loadedEntities[$type] = $this->entityTypeManager->getStorage($type)->loadMultiple($ids);
    }
  }

}
