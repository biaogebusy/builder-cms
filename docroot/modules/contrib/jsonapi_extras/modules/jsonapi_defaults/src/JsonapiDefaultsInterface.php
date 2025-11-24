<?php

namespace Drupal\jsonapi_defaults;

use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi_extras\Entity\JsonapiResourceConfig;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines interface for the JsonapiDefaults service.
 */
interface JsonapiDefaultsInterface {

  /**
   * Get the jsonapi_resource_config from the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Symfony\Component\HttpFoundation\ResourceType|null $resourceType
   *   The JSON:API resource type.
   *
   * @return \Drupal\jsonapi_extras\Entity\JsonapiResourceConfig|null
   *   The jsonapi_resource_config entity or NULL.
   */
  public function getResourceConfigFromRequest(Request $request, ?ResourceType $resourceType = NULL): ?JsonapiResourceConfig;

  /**
   * Returns the correct resource type when operating on related fields.
   *
   * @param string|null $related_field
   *   The name of the related field to use. NULL if not using a related field.
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The resource type straight from the request.
   *
   * @return \Drupal\jsonapi\ResourceType\ResourceType
   *   The resource type to use to load the includes.
   *
   * @throws \LengthException
   *   If there is more than one relatable resource type.
   */
  public static function correctResourceTypeOnRelated(?string $related_field, ResourceType $resource_type): ?ResourceType;

}
