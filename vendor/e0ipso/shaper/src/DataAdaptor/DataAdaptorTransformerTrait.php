<?php

namespace Shaper\DataAdaptor;

use Shaper\Util\Context;

trait DataAdaptorTransformerTrait {

  /**
   * {@inheritdoc}
   */
  public function transform($data, Context $context = NULL) {
    if (!isset($context)) {
      $context = new Context();
    }
    if (!$this->conformsToExpectedInputShape($data, $context)) {
      /** @var \Shaper\Validator\ValidateableInterface $validator */
      $validator = $this->getInputValidator();
      $message = sprintf(
        'Adaptor %s received invalid input data: %s',
        __CLASS__,
        json_encode($validator->getErrors(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)
      );
      throw new \TypeError($message);
    }
    $output = $this->doTransform($data, $context);
    if (!$this->conformsToInternalShape($output, $context)) {
      /** @var \Shaper\Validator\ValidateableInterface $validator */
      $validator = $this->getInternalValidator();
      $message = sprintf(
        'Adaptor %s returned invalid output data: %s',
        __CLASS__,
        json_encode($validator->getErrors(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)
      );
      throw new \TypeError($message);
    }
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function undoTransform($data, Context $context = NULL) {
    if (!isset($context)) {
      $context = new Context();
    }
    if (!$this->conformsToInternalShape($data, $context)) {
      /** @var \Shaper\Validator\ValidateableInterface $validator */
      $validator = $this->getInternalValidator();
      $message = sprintf(
        'Adaptor %s received invalid input data: %s',
        __CLASS__,
        json_encode($validator->getErrors(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)
      );
      throw new \TypeError($message);
    }
    $output = $this->doUndoTransform($data, $context);
    if (!$this->conformsToOutputShape($output, $context)) {
      /** @var \Shaper\Validator\ValidateableInterface $validator */
      $validator = $this->getOutputValidator();
      $message = sprintf(
        'Adaptor %s returned invalid output data: %s',
        __CLASS__,
        json_encode($validator->getErrors(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)
      );
      throw new \TypeError($message);
    }
    return $output;
  }

  /**
   * Transforms incoming data into another shape.
   *
   * This method will validate data coming in and going out using validators.
   *
   * @param mixed $data
   *   The data to transform.
   * @param \Shaper\Util\Context $context
   *   Additional information that will affect how the data is transformed.
   *
   * @return mixed
   *   The data in the new shape.
   *
   * @throws \TypeError
   *   When the transformation cannot be applied.
   */
  abstract protected function doTransform($data, Context $context);

  /**
   * Transforms outgoing data into another shape.
   *
   * This method will validate data coming in and going out using validators.
   *
   * @param mixed $data
   *   The data to transform.
   * @param \Shaper\Util\Context $context
   *   Additional information that will affect how the data is transformed.
   *
   * @return mixed
   *   The data in the new shape.
   *
   * @throws \TypeError
   *   When the transformation cannot be applied.
   */
  abstract protected function doUndoTransform($data, Context $context);

}
