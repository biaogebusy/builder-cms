<?php

namespace Drupal\ctools\Plugin\Deriver;

use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\field\FieldConfigInterface;

/**
 * Derives relationships for typed data.
 */
class TypedDataRelationshipDeriver extends TypedDataPropertyDeriverBase implements ContainerDeriverInterface {

  /**
   * {@inheritdoc}
   */
  protected function generateDerivativeDefinition($base_plugin_definition, $data_type_id, $data_type_definition, DataDefinitionInterface $base_definition, $property_name, DataDefinitionInterface $property_definition) {
    $bundle_info = $base_definition->getConstraint('Bundle');
    $entity_type = $base_definition->getConstraint('EntityType');

    // In Drupal 11.x+ the EntityType constraint is stored as
    // ['type' => 'node'].
    if (is_array($entity_type)) {
      $entity_type = $entity_type['type'] ?? reset($entity_type);
    }

    // If EntityType constraint is not set, extract it from the data type ID
    // (e.g., entity:node or entity:node:page).
    if (!$entity_type && strpos($data_type_id, 'entity:') === 0) {
      $parts = explode(':', $data_type_id);
      $entity_type = $parts[1] ?? NULL;
    }

    // Normalize $bundle_info to a flat list of bundle names, supporting both
    // the D11.4+ format (['bundle' => ['page']]) and the old flat format.
    $bundle_names = self::extractBundleNames($bundle_info);

    // Identify base definitions that appear on bundle-able entities.
    if ($bundle_names && $entity_type && is_string($entity_type)) {
      $base_data_type = 'entity:' . $entity_type;
    }
    // Otherwise, just use the raw data type identifier.
    else {
      $base_data_type = $data_type_id;
    }

    // Extract bundle info from the data type ID if not set on the definition.
    if (!$bundle_names && strpos($data_type_id, 'entity:') === 0) {
      $parts = explode(':', $data_type_id);
      if (isset($parts[2])) {
        $bundle_names = [$parts[2]];
      }
    }

    // If we've not processed this thing before.
    if (!isset($this->derivatives[$base_data_type . ':' . $property_name])) {
      $derivative = $base_plugin_definition;

      // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
      $derivative['label'] = $this->t($this->label, [
        '@property' => $property_definition->getLabel(),
        '@base' => $data_type_definition['label'],
      ]);

      $main_property = $property_definition->getFieldStorageDefinition()->getPropertyDefinition($property_definition->getFieldStorageDefinition()->getMainPropertyName());
      if ($main_property) {
        $derivative['data_type'] = $main_property->getDataType();
        $derivative['property_name'] = $property_name;
        if (strpos($base_data_type, 'entity:') === 0) {
          $context_definition = new EntityContextDefinition($base_data_type, $this->typedDataManager->createDataDefinition($base_data_type));
        }
        else {
          $context_definition = new ContextDefinition($base_data_type, $this->typedDataManager->createDataDefinition($base_data_type));
        }
        // Add the constraints of the base definition to the context definition.
        if ($bundle_names) {
          $context_definition->addConstraint('Bundle', self::bundleConstraintValue($bundle_names));
        }
        $derivative['context_definitions'] = [
          'base' => $context_definition,
        ];
        $derivative['property_name'] = $property_name;

        $this->derivatives[$base_data_type . ':' . $property_name] = $derivative;
      }
    }
    // Individual fields can be on multiple bundles.
    elseif ($property_definition instanceof FieldConfigInterface) {
      // We should only end up in here on entity bundles.
      $derivative = $this->derivatives[$base_data_type . ':' . $property_name];
      // Update label.
      /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $label */
      $label = $derivative['label'];
      [,, $argument_name] = explode(':', $data_type_id);
      $arguments = $label->getArguments();
      $arguments['@' . $argument_name] = $data_type_definition['label'];
      $string_args = $arguments;
      array_shift($string_args);
      $last = array_slice($string_args, -1);
      // The slice doesn't remove, so do that now.
      array_pop($string_args);
      if (count($string_args) >= 2) {
        $string = '@property from ' . implode(', ', array_keys($string_args)) . ' and ' . array_keys($last)[0];
      }
      else {
        $string = '@property from @base and ' . array_keys($last)[0];
      }
      // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
      $this->derivatives[$base_data_type . ':' . $property_name]['label'] = $this->t($string, $arguments);
      if ($bundle_names) {
        // Merge bundle constraints by accumulating all bundle names.
        $context_definition = $derivative['context_definitions']['base'];
        $existing = self::extractBundleNames($context_definition->getConstraint('Bundle'));
        $merged = array_values(array_unique(array_merge($existing, $bundle_names)));
        $context_definition->addConstraint('Bundle', self::bundleConstraintValue($merged));
      }
    }
  }

  /**
   * Builds a 'Bundle' constraint value in the format the running core expects.
   *
   * Drupal 11.4 changed EntityDataDefinition to store the bundle list under a
   * 'bundle' key (['bundle' => ['page']]). Drupal 10 stores the bare list
   * (['page']) and reads the constraint back verbatim, so handing it the keyed
   * format makes EntityDataDefinition::getBundles() return the wrapper array
   * and EntityDataDefinition::getDataType() concatenate an array into a string.
   * Both versions accept either format when *instantiating* BundleConstraint,
   * so it is only the read-back that differs.
   *
   * Rather than compare version strings, ask core how it writes the constraint
   * itself and match that, so this keeps working as the format evolves.
   *
   * @param string[] $bundle_names
   *   A flat list of bundle names.
   *
   * @return array
   *   The constraint value to pass to addConstraint('Bundle', ...).
   */
  protected static function bundleConstraintValue(array $bundle_names): array {
    static $keyed = NULL;
    if ($keyed === NULL) {
      $probe = EntityDataDefinition::create()->setBundles(['probe'])->getConstraint('Bundle');
      $keyed = is_array($probe) && isset($probe['bundle']);
    }
    return $keyed ? ['bundle' => $bundle_names] : $bundle_names;
  }

  /**
   * Extracts a flat list of bundle names from either constraint format.
   *
   * Supports both the D11.4+ format (['bundle' => ['page', 'foo']]) and the
   * old flat format (['page' => 'page'] or ['page', 'foo']).
   *
   * @param mixed $bundle_constraint
   *   The raw value returned by getConstraint('Bundle').
   *
   * @return string[]
   *   A flat list of bundle name strings.
   */
  protected static function extractBundleNames($bundle_constraint): array {
    if (!$bundle_constraint || !is_array($bundle_constraint)) {
      return [];
    }
    if (isset($bundle_constraint['bundle'])) {
      return (array) $bundle_constraint['bundle'];
    }
    // Old format: ['page' => 'page', 'foo' => 'foo'] or ['page', 'foo'].
    return array_values(array_filter($bundle_constraint, 'is_string'));
  }

}
