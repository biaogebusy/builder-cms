<?php

namespace Shaper\Validator;

abstract class ValidateableBase implements ValidateableInterface {

  protected $errors = [];

  /**
   * {@inheritdoc}
   */
  public function getErrors() {
    return $this->errors;
  }

  /**
   * {@inheritdoc}
   */
  public function resetErrors() {
    $this->errors = [];
  }

}
