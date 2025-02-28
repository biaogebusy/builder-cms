<?php

namespace Shaper\Validator;

use JsonSchema\Validator;

class JsonSchemaValidator extends ValidateableBase {

  /**
   * The JSON object with the schema.
   *
   * @var array
   */
  protected $schema;

  /**
   * The schema validator.
   *
   * @var \JsonSchema\Validator
   */
  protected $validator;

  /**
   * The check mode flag for the JSON Schema validator.
   *
   * @var int
   */
  protected $checkMode;

  /**
   * JsonSchema constructor.
   *
   * @param array $schema
   *   The schema.
   * @param \JsonSchema\Validator $validator
   *   The validator.
   */
  public function __construct(array $schema = NULL, Validator $validator = NULL, $mode = NULL) {
    $this->schema = $schema;
    $this->validator = $validator;
    $this->checkMode = $mode;
  }

  /**
   * Sets the validator.
   *
   * @param \JsonSchema\Validator $validator
   *   The object that checks the JSON Schema.
   */
  public function setValidator(Validator $validator) {
    $this->validator = $validator;
  }

  /**
   * Transforms the schema into a JSON object.
   *
   * @return string
   *   The JSON object representation.
   */
  public function toJSON() {
    return json_encode($this->schema);
  }

  /**
   * {@inheritdoc}
   */
  public function isValid($data) {
    $this->resetErrors();
    if (!$this->validator) {
      throw new \InvalidArgumentException('JSON Schema validator needs to be set using setValidator().');
    }
    $num_errors = $this->validator->validate($data, $this->schema, $this->checkMode);
    if ($num_errors) {
      $this->errors = array_merge($this->errors, $this->validator->getErrors());
    }
    return !$num_errors;
  }

  /**
   * Avoid serializing the validator.
   *
   * @return array
   *   The names of the properties to serialize.
   */
  public function __sleep() {
    return ['schema', 'errors'];
  }

  /**
   * Re-attach a validator on de-serialization.
   */
  public function __wakeup() {
    $this->setValidator(new Validator());
  }

  public function getErrors() {
    if ($this->validator) {
      return $this->validator->getErrors();
    }
    return parent::getErrors();
  }

  /**
   * {@inheritdoc}
   */
  public function resetErrors() {
    if ($this->validator) {
      $this->validator->reset();
    }
    $this->errors = [];
  }

}
