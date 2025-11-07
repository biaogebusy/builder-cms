<?php

declare(strict_types = 1);

namespace Drupal\entity_share\Plugin\jsonapi\FieldEnhancer;

use Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerBase;
use Shaper\Util\Context;

/**
 * Prepare paragraph serialized data value.
 *
 * @ResourceFieldEnhancer(
 *   id = "entity_share_serialized_data",
 *   label = @Translation("Serialized Data (Entity Share)"),
 *   description = @Translation("Prepare serialized data to be shared."),
 * )
 */
class EntityShareSerializedDataEnhancer extends ResourceFieldEnhancerBase {

  /**
   * {@inheritdoc}
   */
  protected function doTransform($data, Context $context) {
    return is_array($data) ? ['value' => $data] : $data;
  }

  /**
   * {@inheritdoc}
   */
  protected function doUndoTransform($data, Context $context) {
    return !empty($data['value']) ? $data['value'] : $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputJsonSchema() {
    return [
      'type' => 'object',
    ];
  }

}
