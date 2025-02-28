<?php

namespace Drupal\jsonapi_extras\Normalizer;

use Drupal\jsonapi\ResourceType\ResourceType;

/**
 * Override ContentEntityNormalizer to prepare input.
 */
class ContentEntityDenormalizer extends JsonApiNormalizerDecoratorBase {

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    return parent::denormalize($this->prepareInput($data, $context['resource_type']), $class, $format, $context);
  }

  /**
   * Prepares the input data to create the entity.
   *
   * @param array $data
   *   The input data to modify.
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   Contains the info about the resource type.
   *
   * @return array
   *   The modified input data.
   */
  protected function prepareInput(array $data, ResourceType $resource_type) {
    /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface[] $field_storage_definitions */
    $field_storage_definitions = \Drupal::service('entity_field.manager')
      ->getFieldStorageDefinitions(
        $resource_type->getEntityTypeId()
      );
    $data_internal = [];
    // Translate the public fields into the entity fields.
    foreach ($data as $public_field_name => $field_value) {
      // Skip any disabled field.
      $internal_name = $resource_type->getInternalName($public_field_name);
      $entity_type_id = $resource_type->getEntityTypeId();
      $entity_type_definition = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
      $uuid_key = $entity_type_definition->getKey('uuid');
      if (!$resource_type->isFieldEnabled($internal_name) && $uuid_key !== $internal_name) {
        continue;
      }
      $enhancer = $resource_type->getFieldEnhancer($public_field_name, 'publicName');

      if (isset($field_storage_definitions[$internal_name])) {
        $field_storage_definition = $field_storage_definitions[$internal_name];
        if ($field_storage_definition->getCardinality() === 1) {
          try {
            $field_value = $enhancer ? $enhancer->transform($field_value) : $field_value;
          }
          catch (\TypeError $exception) {
            $field_value = NULL;
          }
        }
        elseif (is_array($field_value)) {
          foreach ($field_value as $key => $individual_field_value) {
            try {
              $field_value[$key] = $enhancer ? $enhancer->transform($individual_field_value) : $individual_field_value;
            }
            catch (\TypeError $exception) {
              $field_value[$key] = NULL;
            }
          }
        }
      }

      $data_internal[$public_field_name] = $field_value;
    }

    return $data_internal;
  }

}
