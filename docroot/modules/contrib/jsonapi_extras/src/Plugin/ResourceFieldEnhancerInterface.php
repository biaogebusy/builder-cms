<?php

namespace Drupal\jsonapi_extras\Plugin;

use Shaper\DataAdaptor\ReversibleTransformationInterface;
use Shaper\DataAdaptor\ReversibleTransformationValidationInterface;
use Shaper\Transformation\TransformationInterface;
use Shaper\Transformation\TransformationValidationInterface;

/**
 * Provides an interface defining a ResourceFieldEnhancer entity.
 */
interface ResourceFieldEnhancerInterface extends TransformationInterface, ReversibleTransformationInterface, TransformationValidationInterface, ReversibleTransformationValidationInterface {

  /**
   * Get the JSON Schema for the new output.
   *
   * @return array
   *   An structured array representing the JSON Schema of the new output.
   */
  public function getOutputJsonSchema();

  /**
   * Get a form element to render the settings.
   *
   * @param array $resource_field_info
   *   The resource field info.
   *
   * @return array
   *   The form element array.
   */
  public function getSettingsForm(array $resource_field_info);

}
