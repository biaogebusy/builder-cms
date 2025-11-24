<?php

namespace Drupal\jsonapi_extras\Plugin\jsonapi\FieldEnhancer;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\jsonapi_extras\Attribute\ResourceFieldEnhancer;
use Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerBase;
use Drupal\options\Plugin\Field\FieldType\ListItemBase;
use Shaper\Util\Context;

/**
 * Perform additional manipulations to list fields.
 */
#[ResourceFieldEnhancer(
  id: 'list',
  label: new TranslatableMarkup('List Field'),
  description: new TranslatableMarkup('Formats a list field based on labels and values.'),
)]
class ListFieldEnhancer extends ResourceFieldEnhancerBase {

  /**
   * {@inheritDoc}
   */
  protected function doTransform($data, Context $context) {
    return is_array($data) ? array_column($data, 'value') : $data;
  }

  /**
   * {@inheritDoc}
   */
  protected function doUndoTransform($data, Context $context) {
    $field_context = $context->offsetGet('field_item_object');
    assert($field_context instanceof ListItemBase);
    $options = $field_context->getPossibleOptions();
    $reformat = static function ($input) use ($options) {
      return [
        'value' => $input,
        'label' => $options[(string) $input] ?? '',
      ];
    };
    return is_array($data) ? array_map($reformat, $data) : $reformat($data);
  }

  /**
   * {@inheritDoc}
   */
  public function getOutputJsonSchema(): array {
    return [
      'type' => 'array',
      'items' => [
        'type' => 'object',
        'properties' => [
          'value' => [
            'anyOf' => [
              ['type' => 'string'],
              ['type' => 'number'],
              ['type' => 'null'],
            ],
          ],
          'label' => [
            'anOf' => [
              ['type' => 'string'],
              ['type' => 'null'],
            ],
          ],
        ],
      ],
    ];
  }

}
