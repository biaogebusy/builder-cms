<?php

namespace Drupal\jsonapi_defaults;

use Drupal\Component\Serialization\Json;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\Routing\Routes;
use Drupal\jsonapi_extras\Entity\JsonapiResourceConfig;
use Drupal\jsonapi_extras\ResourceType\ConfigurableResourceType;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides reusable methods and features.
 */
class JsonapiDefaults implements JsonapiDefaultsInterface {

  /**
   * {@inheritdoc}
   */
  public function getResourceConfigFromRequest(Request $request, ?ResourceType $resourceType = NULL): ?JsonapiResourceConfig {
    $resourceType = !$resourceType ? $request->get(Routes::RESOURCE_TYPE_KEY) : $resourceType;

    if ($resourceType instanceof ConfigurableResourceType) {
      $relatedField = $request->attributes->get('_on_relationship')
        ? NULL
        : $request->attributes->get('related');
      $resourceType = static::correctResourceTypeOnRelated($relatedField, $resourceType);

      if (
          $resourceType instanceof ConfigurableResourceType
        && ($resourceConfig = $resourceType->getJsonapiResourceConfig()) instanceof JsonapiResourceConfig
      ) {
        return $resourceConfig;
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function correctResourceTypeOnRelated(?string $related_field, ResourceType $resource_type): ?ResourceType {
    if (!$related_field) {
      return $resource_type;
    }
    $relatable_resource_types = $resource_type
      ->getRelatableResourceTypesByField($related_field);
    if (count($relatable_resource_types) > 1) {
      $message = sprintf(
        '%s -- %s',
        'Impossible to apply defaults on a related resource with heterogeneous resource types.',
        Json::encode([
          'related_field' => $related_field,
          'host_resource_type' => $resource_type->getPath(),
          'target_resource_types' => array_map(function (ResourceType $resource_type) {
            return $resource_type->getPath();
          }, $relatable_resource_types),
        ])
      );
      throw new \LengthException($message);
    }
    return $relatable_resource_types[0] ?? NULL;
  }

}
