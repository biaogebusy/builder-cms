<?php

namespace Drupal\jsonapi_extras\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\jsonapi\Routing\Routes;
use Drupal\jsonapi_extras\ResourceType\ConfigurableResourceTypeRepository;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * Defines the JSON:API Resource Config entity.
 *
 * @ConfigEntityType(
 *   id = "jsonapi_resource_config",
 *   label = @Translation("JSON:API Resource override"),
 *   label_collection = @Translation("JSON:API Resource overrides"),
 *   label_singular = @Translation("JSON:API resource override"),
 *   label_plural = @Translation("JSON:API resource overrides"),
 *   label_count = @PluralTranslation(
 *     singular = "@count JSON:API resource override",
 *     plural = "@count JSON:API resource overrides",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\jsonapi_extras\JsonapiResourceConfigListBuilder",
 *     "form" = {
 *       "add" = "Drupal\jsonapi_extras\Form\JsonapiResourceConfigForm",
 *       "edit" = "Drupal\jsonapi_extras\Form\JsonapiResourceConfigForm",
 *       "delete" = "Drupal\jsonapi_extras\Form\JsonapiResourceConfigDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     },
 *   },
 *   config_prefix = "jsonapi_resource_config",
 *   admin_permission = "administer site configuration",
 *   static_cache = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "disabled",
 *     "path",
 *     "resourceType",
 *     "resourceFields",
 *   },
 *   links = {
 *     "add-form" = "/admin/config/services/jsonapi/add/resource_types/{entity_type_id}/{bundle}",
 *     "edit-form" = "/admin/config/services/jsonapi/resource_types/{jsonapi_resource_config}/edit",
 *     "delete-form" = "/admin/config/services/jsonapi/resource_types/{jsonapi_resource_config}/delete",
 *     "collection" = "/admin/config/services/jsonapi/resource_types"
 *   }
 * )
 */
class JsonapiResourceConfig extends ConfigEntityBase {

  /**
   * The JSON:API Resource Config ID.
   *
   * @var string
   */
  protected $id;

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    static::rebuildRoutes();
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);
    static::rebuildRoutes();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();
    $id = explode('--', $this->id);
    $typeManager = $this->entityTypeManager();
    $dependency = $typeManager->getDefinition($id[0])->getBundleConfigDependency($id[1]);
    $this->addDependency($dependency['type'], $dependency['name']);
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);
    // The add-form route depends on entity_type_id and bundle.
    if (in_array($rel, ['add-form'])) {
      $parameters = explode('--', $this->id);
      $uri_route_parameters['entity_type_id'] = $parameters[0];
      $uri_route_parameters['bundle'] = $parameters[1];
    }
    return $uri_route_parameters;
  }

  /**
   * Triggers rebuilding of JSON:API routes.
   */
  protected static function rebuildRoutes() {
    try {
      ConfigurableResourceTypeRepository::reset();
      Routes::rebuild();
    }
    catch (ServiceNotFoundException $exception) {
      // This is intentionally empty.
    }
  }

  /**
   * Returns a field mapping as expected by JSON:API 2.x' ResourceType class.
   *
   * @see \Drupal\jsonapi\ResourceType\ResourceType::__construct()
   */
  public function getFieldMapping() {
    $resource_fields = $this->get('resourceFields') ?: [];

    $mapping = [];
    foreach ($resource_fields as $resource_field) {
      $field_name = $resource_field['fieldName'];
      if ($resource_field['disabled'] === TRUE) {
        $mapping[$field_name] = FALSE;
        continue;
      }

      if (($alias = $resource_field['publicName']) && $alias !== $field_name) {
        $mapping[$field_name] = $alias;
        continue;
      }

      $mapping[$field_name] = TRUE;
    }

    return $mapping;
  }

}
