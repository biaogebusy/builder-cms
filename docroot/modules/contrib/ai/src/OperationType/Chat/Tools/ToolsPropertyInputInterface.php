<?php

namespace Drupal\ai\OperationType\Chat\Tools;

/**
 * The property interface.
 *
 * @phpstan-type MappedEnum array{const: string|int, title: string}
 */
interface ToolsPropertyInputInterface {

  /**
   * The constructor.
   *
   * @param string $name
   *   The name of the property.
   * @param array $property
   *   The property array.
   */
  public function __construct(string $name = "", array $property = []);

  /**
   * Get property name.
   *
   * This is the data name and should be lowercase alphanumeric with
   * underscores.
   *
   * @return string
   *   The name of the property.
   */
  public function getName(): string;

  /**
   * Set the name of the property.
   *
   * This is the data name and should be lowercase alphanumeric with
   * underscores.
   *
   * @param string $name
   *   The name of the property.
   */
  public function setName(string $name);

  /**
   * Get the description of the property.
   *
   * One to two sentences describing the property, so the AI knows what it is.
   *
   * @return string
   *   The description of the property.
   */
  public function getDescription(): string;

  /**
   * Set the description of the property.
   *
   * One to two sentences describing the property, so the AI knows what it is.
   *
   * @param string $description
   *   The description of the property.
   */
  public function setDescription(string $description);

  /**
   * Get the type of the property.
   *
   * The type of the property. Normally supported by most models are:
   * string, integer, number, null, boolean, array, object.
   *
   * @return string|array
   *   The type(s) of the property.
   */
  public function getType(): string|array;

  /**
   * Set the type of the property.
   *
   * The type of the property. Normally supported by most models are:
   * string, int, float, bool, array, object.
   * If you give an array, it should start with anyOf, allOf, oneOf, or not.
   * If not given, it will default to anyOf
   * Example: ['string', 'integer'] or ['allOf' => ['string', 'integer']].
   *
   * @param string|array $type
   *   The types of the property.
   */
  public function setType(string|array $type);

  /**
   * Get options (enum) for the property.
   *
   * Usually only ordered arrays are supported. If the array is associative,
   * the keys will be used as the values.
   *
   * @return string[]|int[]|MappedEnum[]|null
   *   The options for the property.
   */
  public function getEnum(): ?array;

  /**
   * Set the options (enum) for the property.
   *
   * @param string[]|int[]|MappedEnum[]|null $options
   *   The options for the property. An array of either:
   *   - String/integer
   *   - An array map of const/title for key/label pairs.
   *   These cannot be mixed.
   */
  public function setEnum(?array $options);

  /**
   * Get the default value for the property.
   *
   * Default values can be set to give something to the AI to return if the
   * property makes no sense to be set.
   *
   * @return mixed
   *   The default value for the property.
   */
  public function getDefault();

  /**
   * Set the default value for the property.
   *
   * Default values can be set to give something to the AI to return if the
   * property makes no sense to be set.
   *
   * @param mixed $default
   *   The default value for the property.
   */
  public function setDefault($default);

  /**
   * Get the example value for the property.
   *
   * Example values can be set to give the AI an example of what the property
   * should look like.
   *
   * @return mixed
   *   The example value for the property.
   */
  public function getExampleValue();

  /**
   * Set the example value for the property.
   *
   * Example values can be set to give the AI an example of what the property
   * should look like.
   *
   * @param mixed $example
   *   The example value for the property.
   */
  public function setExampleValue($example);

  /**
   * Get the minimum value for the property.
   *
   * Minimum values can be set to give the AI a range of values to choose from.
   * This is not supported by all providers.
   *
   * @return int|float
   *   The minimum value for the property.
   */
  public function getMinimum();

  /**
   * Set the minimum value for the property.
   *
   * Minimum values can be set to give the AI a range of values to choose from.
   * This is not supported by all providers.
   *
   * @param int|float $minimum
   *   The minimum value for the property.
   */
  public function setMinimum($minimum);

  /**
   * Get the maximum value for the property.
   *
   * Maximum values can be set to give the AI a range of values to choose from.
   * This is not supported by all providers.
   *
   * @return int|float
   *   The maximum value for the property.
   */
  public function getMaximum();

  /**
   * Set the maximum value for the property.
   *
   * Maximum values can be set to give the AI a range of values to choose from.
   * This is not supported by all providers.
   *
   * @param int|float $maximum
   *   The maximum value for the property.
   */
  public function setMaximum($maximum);

  /**
   * Gets the minimum length of the array.
   *
   * Minimum items can be set to give the AI a constraint on the length of the
   * array. This is not supported by all providers.
   *
   * @return int
   *   The minimum length of the array.
   */
  public function getMinItems(): ?int;

  /**
   * Sets the minimum length of the array.
   *
   * Minimum items can be set to give the AI a constraint on the length of the
   * array. This is not supported by all providers.
   *
   * @param int $minItems
   *   The minimum length of the array.
   */
  public function setMinItems(int $minItems);

  /**
   * Gets the maximum length of the array.
   *
   * Maximum items can be set to give the AI a constraint on the length of the
   * array. This is not supported by all providers.
   *
   * @return int
   *   The maximum length of the array.
   */
  public function getMaxItems(): ?int;

  /**
   * Sets the maximum length of the array.
   *
   * Maximum items can be set to give the AI a constraint on the length of the
   * array. This is not supported by all providers.
   *
   * @param int $maxItems
   *   The maximum length of the array.
   */
  public function setMaxItems(int $maxItems);

  /**
   * Gets the minimum length of the property.
   *
   * Minimum lengths can be set to give the AI a constraint on the length of the
   * string. This is not supported by all providers.
   *
   * @return int
   *   The minimum length of the property.
   */
  public function getMinLength(): ?int;

  /**
   * Sets the minimum length of the property.
   *
   * Minimum lengths can be set to give the AI a constraint on the length of the
   * string. This is not supported by all providers.
   *
   * @param int $minLength
   *   The minimum length of the property.
   */
  public function setMinLength(int $minLength);

  /**
   * Gets the maximum length of the property.
   *
   * Maximum lengths can be set to give the AI a constraint on the length of the
   * string. This is not supported by all providers.
   *
   * @return int
   *   The maximum length of the property.
   */
  public function getMaxLength(): ?int;

  /**
   * Sets the maximum length of the property.
   *
   * Maximum lengths can be set to give the AI a constraint on the length of the
   * string. This is not supported by all providers.
   *
   * @param int $maxLength
   *   The maximum length of the property.
   */
  public function setMaxLength(int $maxLength);

  /**
   * Gets the pattern of the property.
   *
   * Patterns can be set to give the AI a constraint on the format of the
   * string. This is not supported by all providers.
   *
   * @return string
   *   The pattern of the property.
   */
  public function getPattern(): string;

  /**
   * Sets the pattern of the property.
   *
   * Patterns can be set to give the AI a constraint on the format of the
   * string. This is not supported by all providers.
   *
   * @param string $pattern
   *   The pattern of the property.
   */
  public function setPattern(string $pattern);

  /**
   * Gets the format of the property.
   *
   * Formats can be set to give the AI a hint on the format of the string. For
   * example, "date-time" or "email". This is not supported by all providers.
   *
   * @return string
   *   The format of the property.
   */
  public function getFormat(): string;

  /**
   * Sets the format of the property.
   *
   * Formats can be set to give the AI a hint on the format of the string. For
   * example, "date-time" or "email". This is not supported by all providers.
   *
   * @param string $format
   *   The format of the property.
   */
  public function setFormat(string $format);

  /**
   * Get the child properties.
   *
   * If the property is a complex type, it can have child properties. This is
   * NOT supported in most function calling.
   *
   * @return \Drupal\ai\OperationType\Chat\PropertyInterface[]
   *   The child properties.
   */
  public function getProperties(): ?array;

  /**
   * Set the child properties.
   *
   * @param \Drupal\ai\OperationType\Chat\PropertyInterface[] $properties
   *   The child properties.
   */
  public function setProperties(array $properties);

  /**
   * Get if the property is required.
   *
   * @return bool
   *   If the property is required.
   */
  public function isRequired(): bool;

  /**
   * Set if the property is required.
   *
   * @param bool $required
   *   If the property is required.
   */
  public function setRequired(bool $required);

  /**
   * Get items.
   *
   * If the property is an array, it can have items. This is NOT supported in
   * most function calling.
   *
   * @return array|string|null
   *   The items of the property. String means class name.
   */
  public function getItems(): array|string|null;

  /**
   * Set items.
   *
   * If the property is an array, it can have items. This is NOT supported in
   * most function calling.
   *
   * @param array|string $items
   *   The items of the property.
   */
  public function setItems(array|string $items);

  /**
   * Get the constant value for the property.
   *
   * Constant values are used when a value is forced and cannot be changed by
   * the LLM. This is useful for fixed values that must be maintained.
   *
   * @return mixed
   *   The constant value for the property.
   */
  public function getConstant();

  /**
   * Set the constant value for the property.
   *
   * Constant values are used when a value is forced and cannot be changed by
   * the LLM. This is useful for fixed values that must be maintained.
   *
   * @param mixed $constant
   *   The constant value for the property.
   */
  public function setConstant($constant);

  /**
   * Get the custom property values.
   *
   * This is used to get a custom value for the property that is not covered by
   * the other methods.
   *
   * @return array
   *   The custom values of the property.
   */
  public function getCustomValues(): ?array;

  /**
   * Set custom property value.
   *
   * This is used to set a custom value for the property that is not covered by
   * the other methods.
   *
   * @param string $key
   *   The key of the custom value.
   * @param mixed $value
   *   The value of the custom value.
   */
  public function setCustomValue(string $key, $value);

  /**
   * Delete custom property value.
   *
   * This is used to delete a custom value for the property that is not covered
   * by the other methods.
   *
   * @param string $key
   *   The key of the custom value.
   */
  public function deleteCustomValue(string $key);

  /**
   * Sets a property from an array.
   *
   * This is used to create a property from an array. This is used to create
   * properties from JSON or other sources or for ease of use.
   *
   * @param string $name
   *   The name of the property.
   * @param array $property
   *   The property array.
   */
  public function setFromArray(string $name, array $property);

  /**
   * Render property array.
   *
   * This will render the property call to be used in most providers.
   *
   * @return array
   *   The rendered array of the property call.
   */
  public function renderPropertyArray(): array;

}
