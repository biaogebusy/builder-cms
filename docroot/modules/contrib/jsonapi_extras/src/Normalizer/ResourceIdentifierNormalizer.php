<?php

namespace Drupal\jsonapi_extras\Normalizer;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\jsonapi\JsonApiResource\ResourceIdentifier;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\Normalizer\Value\CacheableNormalization;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi_extras\ResourceType\ConfigurableResourceType;
use Drupal\serialization\Normalizer\CacheableNormalizerInterface;
use Shaper\Util\Context;

/**
 * Converts the Drupal entity reference item object to a JSON:API structure.
 *
 * @internal
 */
class ResourceIdentifierNormalizer extends JsonApiNormalizerDecoratorBase {

  /**
   * The resource type repository for changes on the target resource type.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * Instantiates a ResourceIdentifierNormalizer object.
   *
   * @param \Symfony\Component\Serializer\SerializerAwareInterface|\Symfony\Component\Serializer\Normalizer\NormalizerInterface|\Symfony\Component\Serializer\Normalizer\DenormalizerInterface $inner
   *   The decorated normalizer.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The repository.
   */
  public function __construct($inner, ResourceTypeRepositoryInterface $resource_type_repository) {
    parent::__construct($inner);
    $this->resourceTypeRepository = $resource_type_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($field, $format = NULL, array $context = []) {
    assert($field instanceof ResourceIdentifier);
    $normalized_output = parent::normalize($field, $format, $context);
    assert($normalized_output instanceof CacheableNormalization);
    if (!isset($context['resource_object'])) {
      return $normalized_output;
    }
    $resource_object = $context['resource_object'];
    // Find the name of the field being normalized. This is unreasonably more
    // contrived than one could expect for ResourceIdentifiers.
    $resource_type = $resource_object->getResourceType();
    $field_name = $this->guessFieldName($field->getId(), $resource_object);
    if (!$field_name) {
      return $normalized_output;
    }
    $enhancer = $resource_type->getFieldEnhancer($field_name);
    if (!$enhancer) {
      return $normalized_output;
    }
    $cacheability = CacheableMetadata::createFromObject($normalized_output)
      ->addCacheTags(['config:jsonapi_resource_config_list']);
    // Apply any enhancements necessary.
    $context = new Context($context);
    $context->offsetSet('field_resource_identifier', $field);
    $context->offsetSet(CacheableNormalizerInterface::SERIALIZATION_CONTEXT_CACHEABILITY, $cacheability);
    $transformed = $enhancer->undoTransform(
      $normalized_output->getNormalization(),
      $context
    );

    return new CacheableNormalization(
      // This was passed by reference but often, merging creates a new object.
      $context->offsetGet(CacheableNormalizerInterface::SERIALIZATION_CONTEXT_CACHEABILITY),
      array_intersect_key($transformed, array_flip(['id', 'type', 'meta']))
    );
  }

  /**
   * Guesses the field name of a resource identifier pointing to a UUID.
   *
   * @param string $uuid
   *   The uuid being referenced.
   * @param \Drupal\jsonapi\JsonApiResource\ResourceObject $resource_object
   *   The object being normalized.
   *
   * @return string|null
   *   The field name.
   */
  protected function guessFieldName($uuid, ResourceObject $resource_object) {
    $resource_type = $resource_object->getResourceType();
    assert($resource_type instanceof ConfigurableResourceType);
    // From the resource object get all the reference fields.
    $reference_field_names = array_keys($resource_type->getRelatableResourceTypes());
    // Only consider the fields that contain enhancers. This is to improve
    // performance. Discard the candidates that will not have an enhancer.
    $ref_enhancers = array_filter(array_map(function ($public_field_name) use ($resource_type) {
      return $resource_type->getFieldEnhancer($public_field_name, 'publicName');
    }, array_combine($reference_field_names, $reference_field_names)));
    // Get the field objects of the reference fields that have enhancers.
    $reference_fields = array_intersect_key(
      $resource_object->getFields(),
      array_flip(array_keys($ref_enhancers))
    );
    $reference_fields = array_filter($reference_fields, function ($reference_field) {
      // This is certainly a limitation.
      return $reference_field instanceof EntityReferenceFieldItemListInterface;
    });
    return array_reduce(
      $reference_fields,
      function ($field_name, EntityReferenceFieldItemListInterface $object_field) use ($uuid) {
        if ($field_name) {
          return $field_name;
        }
        $referenced_entities = $object_field->referencedEntities();
        // If any of the referenced entities contains the UUID of the field
        // being normalized, then we have our field name.
        $matches = array_filter(
          $referenced_entities,
          function (EntityInterface $referenced_entity) use ($uuid) {
            return $uuid === $referenced_entity->uuid();
          }
        );
        return empty($matches) ? NULL : $object_field->getName();
      }
    );
  }

}
