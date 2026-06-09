<?php

namespace Drupal\ai\Utility;

use Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInput;
use Drupal\ai\Traits\Utility\FunctionCallTrait;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\Validator\Constraints\Choice;

/**
 * Context definition normalizer.
 */
class ContextDefinitionNormalizer {

  use FunctionCallTrait;

  /**
   * Constructs Context Definition Normalizer service.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(protected readonly ModuleHandlerInterface $moduleHandler) {
  }

  /**
   * Normalize the context definition.
   *
   * @param array $context_definitions
   *   The context definition.
   *
   * @return array
   *   The normalized context definition.
   */
  public function normalize(array $context_definitions): array {
    $properties = [];
    foreach ($context_definitions as $key => $definition) {
      /** @var \Drupal\Component\Plugin\Context\ContextDefinitionInterface $definition */
      // Tmp fix.
      $key = str_replace(':', '__colon__', $key);
      $property = new ToolsPropertyInput($key);
      $property->setType($this->mapType($definition->getDataType()));
      $property->setDescription($definition->getLabel() . ' ' . $definition->getDescription());
      if ($definition->getDefaultValue()) {
        $property->setDefault($definition->getDefaultValue());
      }
      $property->setRequired($definition->isRequired());

      // Map constraints to properties.
      // @todo Make plugins that map from existing constraints?
      $constraints = $definition->getConstraints();

      // Check for constant value constraint.
      if (isset($constraints['FixedValue'])) {
        // Extract value from constraint object or array structure.
        if (is_object($constraints['FixedValue']) && property_exists($constraints['FixedValue'], 'value')) {
          $property->setConstant($constraints['FixedValue']->value);
        }
        elseif (is_array($constraints['FixedValue']) && isset($constraints['FixedValue']['value'])) {
          $property->setConstant($constraints['FixedValue']['value']);
        }
        else {
          $property->setConstant($constraints['FixedValue']);
        }
      }
      if (isset($constraints['Choice'])) {
        if (isset($constraints['Choice']['choices'])) {
          $property->setEnum($constraints['Choice']['choices']);
        }
        elseif (isset($constraints['Choice']['callback'])) {
          $property->setEnum(call_user_func($constraints['Choice']['callback']));
        }
        elseif (is_array($constraints['Choice'])) {
          $property->setEnum($constraints['Choice']);
        }
      }
      if (isset($constraints['AllowedValues'])) {
        if ($constraints['AllowedValues'] instanceof Choice) {
          /** @var \Symfony\Component\Validator\Constraints\Choice $choice */
          $choice = $constraints['AllowedValues'];
          $property->setEnum($choice->choices);
        }
        elseif (isset($constraints['AllowedValues']['choices'])) {
          $property->setEnum($constraints['AllowedValues']['choices']);
        }
        elseif (isset($constraints['AllowedValues']['callback'])) {
          $property->setEnum(call_user_func($constraints['AllowedValues']['callback']));
        }
        elseif (is_array($constraints['AllowedValues'])) {
          $property->setEnum($constraints['AllowedValues']);
        }
      }
      if (isset($constraints['Length'])) {
        if (isset($constraints['Length']['min'])) {
          $property->setMinLength($constraints['Length']['min']);
        }
        if (isset($constraints['Length']['max'])) {
          $property->setMaxLength($constraints['Length']['max']);
        }
      }

      if (isset($constraints['Range'])) {
        if (isset($constraints['Range']['min'])) {
          $property->setMinimum($constraints['Range']['min']);
        }
        if (isset($constraints['Range']['max'])) {
          $property->setMaximum($constraints['Range']['max']);
        }
      }

      // Dumb undefined lists are always strings.
      if ($definition->getDataType() === 'list') {
        $property->setType('array');
        $property->setItems(['type' => 'string']);
        if ($property->isRequired()) {
          $property->setMinItems(1);
        }
      }

      // Simple lists.
      if (isset($constraints['SimpleToolItems'])) {
        $property->setType('array');
        $property->setItems($constraints['SimpleToolItems']);
      }

      // Complex lists.
      if (isset($constraints['ComplexToolItems'])) {
        $property->setType('array');
        $sub_item = $this->getFunctionCallPluginManager()->getFunctionCallFromClass($constraints['ComplexToolItems']);
        $normalized = $sub_item->normalize()->renderFunctionArray();
        // We only care about parameters.
        $property->setItems($normalized['parameters'] ?? []);
      }

      // Add a hook alter here to allow other modules to modify the property.
      $this->moduleHandler->alter('ai_tools_property', $property, $definition);

      // If multiple, wrap in array.
      if ($definition->isMultiple()) {
        $array_property = new ToolsPropertyInput($key);
        $array_property->setType('array');
        $array_property->setDescription($property->getDescription());
        $array_property->setItems($property->renderPropertyArray());
        if ($property->isRequired()) {
          $array_property->setMinItems(1);
        }
        $properties[] = $array_property;
      }
      else {
        $properties[] = $property;
      }
    }
    return $properties;
  }

  /**
   * Map types.
   *
   * @param string $type
   *   The type.
   *
   * @return string
   *   The mapped type.
   */
  public function mapType(string $type): string {
    // If it string, integer, boolean, array, number, null its ok.
    $map = [
      'string' => 'string',
      'integer' => 'integer',
      'boolean' => 'boolean',
      'array' => 'array',
      'number' => 'number',
      'null' => 'null',
      'float' => 'number',
      'double' => 'number',
      'decimal' => 'number',
    ];
    return $map[$type] ?? 'string';
  }

}
