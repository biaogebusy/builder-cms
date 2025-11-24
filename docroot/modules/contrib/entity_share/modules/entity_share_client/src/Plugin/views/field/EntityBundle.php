<?php

namespace Drupal\entity_share_client\Plugin\views\field;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Views field plugin for the entity bundle as a label.
 */
#[ViewsField('entity_share_client_entity_bundle')]
class EntityBundle extends FieldPluginBase {

  /**
   * Array of labels.
   *
   * @var array
   */
  protected $loadedLabels = [];


  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.bundle.info'),
    );
  }

  /**
   * Creates a EntityBundle instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);

    // Probably a dirty hack, but this plugin is only for us and don't have time
    // for rabbithole.
    $import_status_entity = $values->_entity;

    $entity_type_id = $import_status_entity->entity_type_id->value;

    if (!isset($this->loadedLabels[$entity_type_id][$value])) {
      $this->loadedLabels[$entity_type_id][$value] = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id)[$value]['label'];
    }

    return $this->sanitizeValue($this->loadedLabels[$entity_type_id][$value]);
  }

}
