<?php

declare(strict_types=1);

namespace Drupal\jsonapi_extras\EventSubscriber;

use Drupal\Core\Config\ConfigImportValidateEventSubscriberBase;
use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Config\ConfigImporterEvent;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\jsonapi_extras\ResourceType\ConfigurableResourceType;
use Drupal\jsonapi_extras\ResourceType\ConfigurableResourceTypeRepository;
use Drupal\jsonapi_extras\ResourceType\NullJsonapiResourceConfig;

/**
 * Makes sure that all resource config entities contain settings for all fields.
 *
 * This will avoid the use of default behavior when a field exists in an entity
 * but there is no config about it. This typically happens when the field is
 * added after the resource config was initially saved.
 */
class FieldConfigIntegrityValidation extends ConfigImportValidateEventSubscriberBase {

  /**
   * The configuration manager.
   *
   * @var \Drupal\Core\Config\ConfigManagerInterface
   */
  private ConfigManagerInterface $configManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * The resource type repository.
   *
   * @var \Drupal\jsonapi_extras\ResourceType\ConfigurableResourceTypeRepository
   */
  private ConfigurableResourceTypeRepository $resourceTypeRepository;

  /**
   * Creates a new validator.
   *
   * @param \Drupal\Core\Config\ConfigManagerInterface $config_manager
   *   The configuration manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\jsonapi_extras\ResourceType\ConfigurableResourceTypeRepository $resource_type_repository
   *   The resource type repository.
   */
  public function __construct(ConfigManagerInterface $config_manager, EntityTypeManagerInterface $entity_type_manager, ConfigurableResourceTypeRepository $resource_type_repository) {
    $this->configManager = $config_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->resourceTypeRepository = $resource_type_repository;
  }

  /**
   * {@inheritDoc}
   */
  public function onConfigImporterValidate(ConfigImporterEvent $event) {
    $jsonapi_extras_settings = $this->configManager
      ->getConfigFactory()
      ->get('jsonapi_extras.settings');
    if (!$jsonapi_extras_settings->get('validate_configuration_integrity')) {
      // Nothing to do.
      return;
    }
    $config_importer = $event->getConfigImporter();
    // Get the configuration ready to be imported. Future configuration.
    $changelist = $event->getChangelist();
    // Determine if any fields are being updated, if so grab their entity type
    // ID and bundle.
    $changed_info = $this->getChangedFields($changelist, $config_importer);
    array_map(
      function (array $info) use ($config_importer) {
        $entity_type_id = $info['entity_type'];
        $bundle = $info['bundle'] ?? $entity_type_id;
        $field_name = $info['field_name'];
        // First check if the configuration for JSON:API Extras will be
        // installed.
        $resource_config_name = sprintf(
          '%s.%s--%s',
          $this->entityTypeManager->getDefinition('jsonapi_resource_config')->getConfigPrefix(),
          $entity_type_id,
          $bundle
        );
        // Check weather the field changes are accompanied by a resource change.
        $new_resource_config = $config_importer->getStorageComparer()->getSourceStorage()->read($resource_config_name);
        if ($new_resource_config && !empty($new_resource_config['resourceFields'][$field_name])) {
          // All good. There are new fields, but they are coming in with the
          // resource config as well.
          return;
        }
        // Next let's grab the current configuration to see if there was
        // configuration for that field already.
        $resource_type = $this->resourceTypeRepository->get(
          $entity_type_id,
          $bundle,
        );
        if (!$resource_type instanceof ConfigurableResourceType) {
          return;
        }
        // Make sure there is configuration associated to the resource type,
        // otherwise there is nothing to do.
        $current_config = $resource_type->getJsonapiResourceConfig();
        if ($current_config instanceof NullJsonapiResourceConfig) {
          return;
        }
        $missing = !isset($current_config->get('resourceFields')[$field_name]);
        if ($missing) {
          $config_importer->logError($this->t(
            'Integrity check failed for the JSON:API Extras configuration. There is no configuration set for the field "@field_name" on the resource "@entity_type--@bundle". To fix this, disable the configuration integrity check (in the JSON:API Extras settings page), so you can import these fields locally. After that configure and re-save this resource type in the JSON:API Extras configuration page (@url). Finally, re-enable the configuration integrity checks and export the configuration again.',
            [
              '@field_name' => $field_name,
              '@entity_type' => $entity_type_id,
              '@bundle' => $bundle,
              '@url' => $current_config->toUrl('edit-form', ['absolute' => TRUE])->toString(TRUE)->getGeneratedUrl(),
            ],
          ));
        }
      },
      $changed_info,
    );
  }

  /**
   * Get information about the fields being changed, if any.
   *
   * @param array $changes_per_operation
   *   The list of changed config names grouped by operation.
   * @param \Drupal\Core\Config\ConfigImporter $importer
   *   The configuration importer.
   *
   * @return array[]
   *   A list of associative arrays, each one containing the field name, bundle,
   *   and entity type of the fields being changed.
   */
  private function getChangedFields(array $changes_per_operation, ConfigImporter $importer): array {
    // We only care about create and update operations.
    $changes_per_operation = array_intersect_key(
      $changes_per_operation,
      array_flip(['create', 'update'])
    );
    // Filter sub-arrays to get config names that correspond to field_config.
    $field_config_names_per_operation = array_map(
      fn(array $config_names) => array_filter(
        $config_names,
        fn(string $config_name) => $this->configManager->getEntityTypeIdByName($config_name) === 'field_config'
      ),
      $changes_per_operation
    );
    // Flatten the array.
    $field_config_names = array_reduce(
      $field_config_names_per_operation,
      static fn(array $carry, array $names) => array_unique([...$carry, ...$names]),
      []
    );
    // Read the configuration object for the field_config coming in, and collect
    // the field name, bundle, and entity type.
    return array_map(
      static fn(string $config_name) => array_intersect_key(
        $importer->getStorageComparer()->getSourceStorage()->read($config_name),
        array_flip(['entity_type', 'bundle', 'field_name'])),
      $field_config_names,
    );
  }

}
