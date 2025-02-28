<?php
/**
 * Created by PhpStorm.
 * User: e0ipso
 * Date: 27/02/2018
 * Time: 13:37
 */

namespace Shaper\Validator;

/**
 * Validator that accepts everything.
 *
 * @package Shaper
 */
class AcceptValidator extends ValidateableBase {

  /**
   * {@inheritdoc}
   */
  public function isValid($data) {
    return TRUE;
  }

}
