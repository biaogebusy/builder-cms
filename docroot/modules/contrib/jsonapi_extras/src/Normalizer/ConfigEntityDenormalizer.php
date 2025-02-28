<?php

namespace Drupal\jsonapi_extras\Normalizer;

use Drupal\jsonapi\ResourceType\ResourceType;

/**
 * Override ConfigEntityNormalizer to prepare input.
 */
class ConfigEntityDenormalizer extends JsonApiNormalizerDecoratorBase {

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    return parent::denormalize($this->prepareInput($data, $context['resource_type']), $class, $format, $context);
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareInput(array $data, ResourceType $resource_type) {
    foreach ($data as $public_field_name => &$field_value) {
      /** @var \Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerInterface $enhancer */
      $enhancer = $resource_type->getFieldEnhancer($public_field_name);
      if (!$enhancer) {
        continue;
      }
      $field_value = $enhancer->transform($field_value);
    }

    return $data;
  }

}
