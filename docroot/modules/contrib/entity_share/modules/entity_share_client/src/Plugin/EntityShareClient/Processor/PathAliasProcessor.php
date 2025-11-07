<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Plugin\EntityShareClient\Processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_share_client\ImportProcessor\ImportProcessorPluginBase;
use Drupal\entity_share_client\RuntimeImportContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Path alias processor.
 *
 * @ImportProcessor(
 *   id = "path_alias_processor",
 *   label = @Translation("Path alias processor"),
 *   description = @Translation("Prepares entity with the right path alias information."),
 *   stages = {
 *     "prepare_importable_entity_data" = -100,
 *   },
 *   locked = false,
 * )
 */
class PathAliasProcessor extends ImportProcessorPluginBase {

  /**
   * Entity Type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param mixed $plugin_id
   *   Plugin id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function prepareImportableEntityData(RuntimeImportContext $runtime_import_context, array &$entity_json_data) {
    $field_mappings = $runtime_import_context->getFieldMappings();
    $parsed_type = explode('--', $entity_json_data['type']);
    $entity_type_id = $parsed_type[0];
    $entity_bundle = $parsed_type[1];

    $path_public_name = FALSE;
    if (isset($field_mappings[$entity_type_id][$entity_bundle]['path'])) {
      $path_public_name = $field_mappings[$entity_type_id][$entity_bundle]['path'];
    }

    if (!empty($entity_json_data['attributes'][$path_public_name])) {
      // We cannot rely on remote path alias to find the local pid, because
      // there could be a scenario where the alias has been updated remotely
      // and the pid cannot be found.
      $path = &$entity_json_data['attributes'][$path_public_name];

      // Try to load the entity by uuid.
      // Already imported entity.
      $storage = $this->entityTypeManager->getStorage($entity_type_id);
      $local_entities = $storage->loadByProperties([
        'uuid' => $entity_json_data['id'],
      ]);

      // Newly imported entity.
      if (empty($local_entities)) {
        // Drop pid. Core must create a new alias.
        unset($path['pid']);
        return;
      }
      $local_entity = reset($local_entities);

      // The already imported entity does not have an alias yet.
      if (empty($local_entity->path->pid)) {
        // Drop pid. Core must create a new alias.
        unset($path['pid']);
        return;
      }

      // Already imported entity with an existing alias.
      // Override pid from server side with local pid to avoid collision,
      // overriding another unrelated path alias.
      $path['pid'] = $local_entity->path->pid;
    }
  }

}
