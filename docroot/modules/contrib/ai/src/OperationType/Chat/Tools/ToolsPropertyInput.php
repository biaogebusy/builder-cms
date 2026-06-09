<?php

namespace Drupal\ai\OperationType\Chat\Tools;

use Drupal\ai\Traits\Helper\ToolsCallingTrait;

/**
 * The property interface.
 */
class ToolsPropertyInput implements ToolsPropertyInputInterface {

  use ToolsCallingTrait;

  /**
   * The name of the property.
   *
   * @var string
   */
  private string $name = '';

  /**
   * The description of the property.
   *
   * @var string
   */
  private string $description = '';

  /**
   * The type of the property.
   *
   * @var string|array
   */
  private string|array $type;

  /**
   * The options of the property.
   *
   * @var array
   */
  private ?array $options = NULL;

  /**
   * The properties of the property.
   *
   * @var array
   */
  private ?array $properties = NULL;

  /**
   * The default value of the property.
   *
   * @var mixed
   */
  private $default = NULL;

  /**
   * The example value of the property.
   *
   * @var mixed
   */
  private $exampleValue = NULL;

  /**
   * The minimum value of the property.
   *
   * @var mixed
   */
  private $minimum = NULL;

  /**
   * The maximum value of the property.
   *
   * @var mixed
   */
  private $maximum = NULL;

  /**
   * The minimum number of items in an array.
   *
   * @var mixed
   */
  private $minItems = NULL;

  /**
   * The maximum number of items in an array.
   *
   * @var mixed
   */
  private $maxItems = NULL;

  /**
   * The minimum length of the property.
   *
   * @var int
   */
  private ?int $minLength = NULL;

  /**
   * The maximum length of the property.
   *
   * @var int
   */
  private ?int $maxLength = NULL;

  /**
   * The pattern of the property.
   *
   * @var string
   */
  private string $pattern = '';

  /**
   * The format of the property.
   *
   * @var string
   */
  private string $format = '';

  /**
   * If the property is required.
   *
   * @var bool
   */
  private bool $required = FALSE;

  /**
   * The items.
   *
   * @var array|string
   */
  private $items = NULL;

  /**
   * The constant value of the property.
   *
   * @var mixed
   */
  private $constant = NULL;

  /**
   * The custom values of the property.
   *
   * @var array
   */
  private array $customValues = [];

  /**
   * {@inheritDoc}
   */
  public function __construct(string $name = "", array $property = []) {
    if (!empty($name) && !empty($property)) {
      $this->setFromArray($name, $property);
    }
    elseif (!empty($name)) {
      $this->setName($name);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * {@inheritDoc}
   */
  public function setName(string $name) {
    $this->verifyFunctionName($name);
    $this->name = $name;
  }

  /**
   * {@inheritDoc}
   */
  public function getDescription(): string {
    return $this->description;
  }

  /**
   * {@inheritDoc}
   */
  public function setDescription(string $description) {
    $this->description = $description;
  }

  /**
   * {@inheritDoc}
   */
  public function getType(): string|array {
    return $this->type;
  }

  /**
   * {@inheritDoc}
   */
  public function setType(string|array $type) {
    if (is_string($type)) {
      $this->verifyFunctionName($type);
    }
    else {
      if (in_array(key($type), [
        'anyOf',
        'allOf',
        'oneOf',
        'not',
      ])) {
        if (array_keys($type[key($type)]) !== range(0, count($type[key($type)]) - 1)) {
          throw new \InvalidArgumentException('The type should be a string, an numeric array or an array with anyOf, allOf, oneOf, or not with an numeric array in it.');
        }
      }
      elseif (array_keys($type) == range(0, count($type) - 1)) {
        $old_type = $type;
        unset($type);
        $type['anyOf'] = $old_type;
      }
      else {
        throw new \InvalidArgumentException('The type should be a string, an numeric array or an array with anyOf, allOf, oneOf, or not.');
      }
    }
    $this->type = $type;
  }

  /**
   * {@inheritDoc}
   */
  public function getEnum(): ?array {
    return $this->options;
  }

  /**
   * {@inheritDoc}
   */
  public function setEnum(?array $options): void {
    foreach ($options as $value) {
      if (is_array($value)) {
        $keys = array_keys($value);
        if ($keys != ['const', 'title']) {
          throw new \InvalidArgumentException('The options should be a flat array and only have const/title properties.');
        }
      }
    }
    $this->options = $options;
  }

  /**
   * {@inheritDoc}
   */
  public function getProperties(): ?array {
    return $this->properties;
  }

  /**
   * {@inheritDoc}
   */
  public function setProperties(array $properties) {
    foreach ($properties as $property) {
      if (!$property instanceof ToolsPropertyInputInterface) {
        throw new \InvalidArgumentException('The properties should be an array of ToolsPropertyInputInterface.');
      }
      $this->properties[$property->getName()] = $property;
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getDefault() {
    return $this->default;
  }

  /**
   * {@inheritDoc}
   */
  public function setDefault($default) {
    $this->default = $default;
  }

  /**
   * {@inheritDoc}
   */
  public function getExampleValue() {
    return $this->exampleValue;
  }

  /**
   * {@inheritDoc}
   */
  public function setExampleValue($exampleValue) {
    $this->exampleValue = $exampleValue;
  }

  /**
   * {@inheritDoc}
   */
  public function getMinimum() {
    return $this->minimum;
  }

  /**
   * {@inheritDoc}
   */
  public function setMinimum($minimum) {
    $this->minimum = $minimum;
  }

  /**
   * {@inheritDoc}
   */
  public function getMaximum() {
    return $this->maximum;
  }

  /**
   * {@inheritDoc}
   */
  public function setMaximum($maximum) {
    $this->maximum = $maximum;
  }

  /**
   * {@inheritDoc}
   */
  public function getMinItems(): ?int {
    return $this->minItems;
  }

  /**
   * {@inheritDoc}
   */
  public function setMinItems(int $minItems) {
    $this->minItems = $minItems;
  }

  /**
   * {@inheritDoc}
   */
  public function getMaxItems(): ?int {
    return $this->maxItems;
  }

  /**
   * {@inheritDoc}
   */
  public function setMaxItems(int $maxItems) {
    $this->maxItems = $maxItems;
  }

  /**
   * {@inheritDoc}
   */
  public function getMinLength(): ?int {
    return $this->minLength;
  }

  /**
   * {@inheritDoc}
   */
  public function setMinLength(int $minLength) {
    $this->minLength = $minLength;
  }

  /**
   * {@inheritDoc}
   */
  public function getMaxLength(): ?int {
    return $this->maxLength;
  }

  /**
   * {@inheritDoc}
   */
  public function setMaxLength(int $maxLength) {
    $this->maxLength = $maxLength;
  }

  /**
   * {@inheritDoc}
   */
  public function getPattern(): string {
    return $this->pattern;
  }

  /**
   * {@inheritDoc}
   */
  public function setPattern(string $pattern) {
    $this->pattern = $pattern;
  }

  /**
   * {@inheritDoc}
   */
  public function getFormat(): string {
    return $this->format;
  }

  /**
   * {@inheritDoc}
   */
  public function setFormat(string $format) {
    $this->format = $format;
  }

  /**
   * {@inheritDoc}
   */
  public function isRequired(): bool {
    return $this->required;
  }

  /**
   * {@inheritDoc}
   */
  public function setRequired(bool $required) {
    $this->required = $required;
  }

  /**
   * {@inheritDoc}
   */
  public function getItems(): array|string|null {
    return $this->items;
  }

  /**
   * {@inheritDoc}
   */
  public function setItems(array|string $items) {
    $this->items = $items;
  }

  /**
   * {@inheritDoc}
   */
  public function getConstant() {
    return $this->constant;
  }

  /**
   * {@inheritDoc}
   */
  public function setConstant($constant) {
    $this->constant = $constant;
  }

  /**
   * {@inheritDoc}
   */
  public function getCustomValues(): array {
    return $this->customValues;
  }

  /**
   * {@inheritDoc}
   */
  public function setCustomValue(string $key, $value) {
    $this->customValues[$key] = $value;
  }

  /**
   * {@inheritDoc}
   */
  public function deleteCustomValue(string $key) {
    unset($this->customValues[$key]);
  }

  /**
   * {@inheritDoc}
   */
  public function setFromArray(string $name, array $property) {
    $this->setName($name);
    if (isset($property['description'])) {
      $this->setDescription($property['description']);
    }
    if (isset($property['type'])) {
      $this->setType($property['type']);
    }
    if (isset($property['required'])) {
      $this->setRequired($property['required']);
    }
    if (isset($property['enum'])) {
      $this->setEnum($property['enum']);
    }
    if (isset($property['properties'])) {
      $this->setProperties($property['properties']);
    }
    if (isset($property['default'])) {
      $this->setDefault($property['default']);
    }
    if (isset($property['minimum'])) {
      $this->setMinimum($property['minimum']);
    }
    if (isset($property['maximum'])) {
      $this->setMaximum($property['maximum']);
    }
    if (isset($property['minItems'])) {
      $this->setMinItems($property['minItems']);
    }
    if (isset($property['maxItems'])) {
      $this->setMaxItems($property['maxItems']);
    }
    if (isset($property['minLength'])) {
      $this->setMinLength($property['minLength']);
    }
    if (isset($property['maxLength'])) {
      $this->setMaxLength($property['maxLength']);
    }
    if (isset($property['pattern'])) {
      $this->setPattern($property['pattern']);
    }
    if (isset($property['format'])) {
      $this->setFormat($property['format']);
    }
    if (isset($property['exampleValue'])) {
      $this->setExampleValue($property['exampleValue']);
    }
    if (isset($property['items'])) {
      $this->setItems($property['items']);
    }
    if (isset($property['constant'])) {
      $this->setConstant($property['constant']);
    }
    foreach ($property as $key => $value) {
      if (!in_array($key, [
        'name',
        'description',
        'type',
        'enum',
        'properties',
        'default',
        'minimum',
        'maximum',
        'minLength',
        'maxLength',
        'pattern',
        'format',
        'exampleValue',
        'items',
        'constant',
      ])) {
        $this->setCustomValue($key, $value);
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  public function renderPropertyArray(): array {
    if (empty($this->name) && empty($this->type)) {
      throw new \InvalidArgumentException('The name and type of the property should be set before rendering the property array.');
    }
    $property = [
      'name' => $this->name,
      'type' => $this->type,
    ];

    // Merge the custom values.
    $property = array_merge($property, $this->customValues);

    if (!empty($this->description)) {
      $property['description'] = $this->description;
    }
    if ($this->constant !== NULL) {
      $property['const'] = $this->constant;
      // Non scalar values aren't suitable to be presented as an enum, and can't
      // be added to the description.
      if (is_scalar($this->constant)) {
        $property['enum'] = [$this->constant];
        $property['description'] = trim($property['description'] . " This must always have the value {$this->constant} and cannot be modified.");
      }
      else {
        $property['description'] = trim($property['description'] . " This is a constant and cannot be modified.");
      }
    }
    elseif (!empty($this->options)) {
      // Dicts need to be set on oneOf/anyOf.
      if (is_array($this->options[0])) {
        $property[$this->type === 'array' ? 'anyOf' : 'oneOf'] = $this->options;
      }
      else {
        $property['enum'] = $this->options;
      }
    }
    if (!empty($this->properties)) {
      foreach ($this->properties as $child_property) {
        $property['properties'][$child_property->getName()] = $child_property->renderPropertyArray();
      }
    }
    if (!empty($this->default)) {
      $property['default'] = $this->default;
    }
    if (!empty($this->minimum)) {
      $property['minimum'] = $this->minimum;
    }
    if (!empty($this->maximum)) {
      $property['maximum'] = $this->maximum;
    }
    if (!empty($this->minItems)) {
      $property['minItems'] = $this->minItems;
    }
    if (!empty($this->maxItems)) {
      $property['maxItems'] = $this->maxItems;
    }
    if (!empty($this->minLength)) {
      $property['minLength'] = $this->minLength;
    }
    if (!empty($this->maxLength)) {
      $property['maxLength'] = $this->maxLength;
    }
    if (!empty($this->pattern)) {
      $property['pattern'] = $this->pattern;
    }
    if (!empty($this->format)) {
      $property['format'] = $this->format;
    }
    if (!empty($this->exampleValue)) {
      $property['exampleValue'] = $this->exampleValue;
    }
    if (!empty($this->items)) {
      if (is_string($this->items)) {
        $property['items'] = $this->getChildFunction($this->items)->renderFunctionArray()['parameters'];
      }
      else {
        $property['items'] = $this->items;
      }
    }

    return $property;
  }

  /**
   * Helper function to find a child instance from a class name.
   *
   * @param string $class_name
   *   The class name.
   *
   * @return \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput
   *   The function call input.
   */
  private function getChildFunction(string $class_name): ToolsFunctionInput {
    if (!class_exists($class_name)) {
      throw new \InvalidArgumentException('The class name does not exist.');
    }
    $plugin_manager = $this->getFunctionCallPluginManager();
    $definitions = $plugin_manager->getDefinitions();
    foreach ($definitions as $definition) {
      if ($definition['class'] == $class_name) {
        return $plugin_manager->createInstance($definition['id'])->normalize();
      }
    }
    throw new \InvalidArgumentException('The class is not a valid function call.');
  }

}
