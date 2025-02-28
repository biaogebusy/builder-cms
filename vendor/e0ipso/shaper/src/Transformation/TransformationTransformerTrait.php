<?php

namespace Shaper\Transformation;

use Shaper\Util\Context;

trait TransformationTransformerTrait {

  /**
   * {@inheritdoc}
   */
  public function transform($data, Context $context = NULL) {
    if (!isset($context)) {
      $context = new Context();
    }

    // Error utility.
    $throw = function($message, $arguments = []) {
      /** @var \Shaper\Validator\ValidateableInterface $validator */
      $validator = $this->getInputValidator();
      $options = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;

      throw new \TypeError(strtr($message, $arguments + [
        '{class}' => static::class,
        '{data}' => json_encode($validator->getErrors(), $options)
      ]));
    };

    if (!$this->conformsToExpectedInputShape($data, $context)) {
      $throw('Adaptor {class} received invalid input data: {data}.');
    }

    $output = $this->doTransform($data, $context);
    if (!$this->conformsToOutputShape($output, $context)) {
      $throw('Adaptor {class} returned invalid output data: {data}');
    }

    return $output;
  }

  /**
   * Basic transformation from a shape into another shape.
   *
   * This method does not include validations since they are handled in the
   * calling method.
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

}
