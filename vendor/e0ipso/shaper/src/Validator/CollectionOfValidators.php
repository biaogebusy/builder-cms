<?php

namespace Shaper\Validator;

class CollectionOfValidators extends ValidateableBase {

  /**
   * The name of the class or interface the data must comply.
   *
   * @var string
   */
  protected $itemValidator;

  /**
   * InstanceofValidator constructor.
   *
   * @param ValidateableInterface $item_validator
   *   The validator to apply to each item.
   *
   * @throws \TypeError
   *   If the class or interface does not exist.
   */
  public function __construct(ValidateableInterface $item_validator) {
    $this->itemValidator = $item_validator;
  }

  /**
   * {@inheritdoc}
   */
  public function isValid($data) {
    $this->resetErrors();
    if (!is_array($data)) {
      array_push($this->errors, 'Collection of validators only applies on data arrays.');
      return FALSE;
    }
    // The collection is valid if all the items are valid.
    return array_reduce($data, function ($is_valid, $item) {
      $valid_item = TRUE;
      $is_valid = $is_valid && ($valid_item = $this->itemValidator->isValid($item));
      if (!$valid_item) {
        $this->errors = array_merge($this->errors, $this->itemValidator->getErrors());
      }
      return $is_valid;
    }, TRUE);
  }

}
