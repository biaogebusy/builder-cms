<?php

namespace Drupal\entity_share_client\Plugin\views\field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Views field plugin for the imported entity UUID and JSONAPI link.
 */
#[ViewsField('entity_share_client_uuid')]
class EntityUuid extends FieldPluginBase {

  protected $remoteURLs = [];

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
   * Creates an EntityUuid instance.
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

    // Probably a dirty hack, but this plugin is only for us and don't have
    // time for rabbithole.
    $import_status_entity = $values->_entity;
    $remote_id = $import_status_entity->remote_website->value;

    if (isset($this->remoteURLs[$remote_id])) {
      $jsonapi_url = $this->remoteURLs[$remote_id] . '/jsonapi/' . $import_status_entity->entity_type_id->value . '/' . $import_status_entity->entity_bundle->value . '/' . $value;

      $this->options['alter']['url'] = Url::fromUri($jsonapi_url, [
        'absolute' => TRUE,
      ]);
      $this->options['alter']['make_link'] = TRUE;
    }

    return $this->sanitizeValue($value);
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(&$values) {
    $remotes = $this->entityTypeManager->getStorage('remote')->loadMultiple();
    foreach ($remotes as $remote_id => $remote) {
      $this->remoteURLs[$remote_id] = $remote->get('url');
    }
  }

}
