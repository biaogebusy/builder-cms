<?php

namespace Drupal\jsonapi_extras\Normalizer;

use Drupal\Component\Utility\NestedArray;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi_extras\ResourceType\ConfigurableResourceType;
use Drupal\schemata_json_schema\Normalizer\jsonapi\SchemataSchemaNormalizer as SchemataJsonSchemaSchemataSchemaNormalizer;

/**
 * Applies JSONAPI Extras attribute overrides to entity schemas.
 */
class SchemataSchemaNormalizer extends SchemataJsonSchemaSchemataSchemaNormalizer {

  /**
   * The JSON:API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepository
   */
  protected $resourceTypeRepository;

  /**
   * Constructs a SchemataSchemaNormalizer object.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   A resource repository.
   */
  public function __construct(ResourceTypeRepositoryInterface $resource_type_repository) {
    $this->resourceTypeRepository = $resource_type_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = []) {
    $normalized = parent::normalize($entity, $format, $context);

    // Load the resource type for this entity type and bundle.
    $bundle = $entity->getBundleId();
    $bundle = $bundle ?: $entity->getEntityTypeId();
    $resource_type = $this->resourceTypeRepository->get(
      $entity->getEntityTypeId(),
      $bundle
    );

    if (!$resource_type || !$resource_type instanceof ConfigurableResourceType) {
      return $normalized;
    }

    // Alter the attributes according to the resource config.
    if (!empty($normalized['definitions'])) {
      $root = &$normalized['definitions'];
    }
    else {
      $root = &$normalized['properties']['data']['properties'];
    }
    foreach (['attributes', 'relationships'] as $property_type) {
      if (!isset($root[$property_type]['required'])) {
        $root[$property_type]['required'] = [];
      }
      $properties = NestedArray::getValue($root, [$property_type, 'properties']) ?: [];
      foreach ($properties as $fieldname => $schema) {
        if ($enhancer = $resource_type->getFieldEnhancer($resource_type->getFieldByPublicName($fieldname)->getInternalName())) {
          $root[$property_type]['properties'][$fieldname] = array_merge(
            array_intersect_key($root[$property_type]['properties'][$fieldname],
            array_flip(['title', 'description'])),
            $enhancer->getOutputJsonSchema()
          );
        }
      }
    }

    return $normalized;
  }

}
