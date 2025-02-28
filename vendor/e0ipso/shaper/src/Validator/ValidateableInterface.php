<?php

namespace Shaper\Validator;

interface ValidateableInterface {

  /**
   * Checks that the provided data is valid.
   *
   * @param mixed $data
   *   The data to validate.
   *
   * @return bool
   *   TRUE if the data is valid. FALSE otherwise.
   */
  public function isValid($data);

  /**
   * Get the eventual errors in case validation failed.
   *
   * @return array
   *   The list of errors that happened.
   */
  public function getErrors();

  /**
   * Clears any reported errors.
   *
   * Should be used between validation checks.
   */
  public function resetErrors();

}
