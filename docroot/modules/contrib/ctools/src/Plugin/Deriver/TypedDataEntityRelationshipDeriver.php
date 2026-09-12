<?php

namespace Drupal\ctools\Plugin\Deriver;

use Drupal\Core\TypedData\DataDefinitionInterface;

/**
 * Relationship deriver for typed data entities.
 */
class TypedDataEntityRelationshipDeriver extends TypedDataRelationshipDeriver {

  /**
   * {@inheritdoc}
   */
  protected $label = '@property Entity from @base';

  /**
   * {@inheritdoc}
   */
  protected function generateDerivativeDefinition($base_plugin_definition, $data_type_id, $data_type_definition, DataDefinitionInterface $base_definition, $property_name, DataDefinitionInterface $property_definition) {
    if (method_exists($property_definition, 'getType') && $property_definition->getType() == 'entity_reference') {
      parent::generateDerivativeDefinition($base_plugin_definition, $data_type_id, $data_type_definition, $base_definition, $property_name, $property_definition);

      // Provide the entity type.
      $derivative_id = $data_type_id . ':' . $property_name;
      if (isset($this->derivatives[$derivative_id])) {
        $target_entity_type = $property_definition->getFieldStorageDefinition()->getPropertyDefinition('entity')->getConstraint('EntityType');
        if (is_array($target_entity_type)) {
          $target_entity_type = $target_entity_type['type'] ?? reset($target_entity_type);
        }
        if (is_string($target_entity_type)) {
          $this->derivatives[$derivative_id]['target_entity_type'] = $target_entity_type;
        }
      }
    }
  }

}
