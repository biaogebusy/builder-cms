<?php

namespace Drupal\jsonapi_extras\Normalizer;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi_extras\ResourceType\ConfigurableResourceType;
use Drupal\schemata_json_schema\Normalizer\jsonapi\FieldDefinitionNormalizer as SchemataJsonSchemaFieldDefinitionNormalizer;

/**
 * Applies field enhancer schema changes to field schema.
 */
class SchemaFieldDefinitionNormalizer extends SchemataJsonSchemaFieldDefinitionNormalizer {

  /**
   * The JSON:API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepository
   */
  protected $resourceTypeRepository;

  /**
   * Constructs a SchemaFieldDefinitionNormalizer object.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   A resource type repository.
   */
  public function __construct(ResourceTypeRepositoryInterface $resource_type_repository) {
    $this->resourceTypeRepository = $resource_type_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($field_definition, $format = NULL, array $context = []) {
    assert($field_definition instanceof FieldDefinitionInterface);
    $normalized = parent::normalize($field_definition, $format, $context);

    // Load the resource type for this entity type and bundle.
    $bundle = empty($context['bundleId'])
      ? $context['entityTypeId']
      : $context['bundleId'];
    $resource_type = $this->resourceTypeRepository->get($context['entityTypeId'], $bundle);

    if (!$resource_type || !$resource_type instanceof ConfigurableResourceType) {
      return $normalized;
    }

    $field_name = $context['name'];
    $enhancer = $resource_type->getFieldEnhancer($field_definition->getName());
    if (!$enhancer) {
      return $normalized;
    }
    $parents = ['properties', 'attributes', 'properties', $field_name];
    $original_field_schema = NestedArray::getValue($normalized, $parents);
    $to_copy = ['title', 'description'];
    $field_schema = array_merge(
      $enhancer->getOutputJsonSchema(),
      // Copy *some* properties from the original.
      array_intersect_key($original_field_schema, array_flip($to_copy))
    );
    NestedArray::setValue(
      $normalized,
      $parents,
      $field_schema
    );

    return $normalized;
  }

}
