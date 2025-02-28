<?php

namespace Drupal\search_api\Plugin\search_api\processor;

use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Plugin\search_api\data_type\value\TextValueInterface;
use Drupal\search_api\Processor\FieldsProcessorPluginBase;

/**
 * Makes searches case-insensitive on selected fields.
 *
 * @SearchApiProcessor(
 *   id = "ignorecase",
 *   label = @Translation("Ignore case"),
 *   description = @Translation("Makes searches case-insensitive on selected fields."),
 *   stages = {
 *     "pre_index_save" = 0,
 *     "preprocess_index" = -20,
 *     "preprocess_query" = -20
 *   }
 * )
 */
class IgnoreCase extends FieldsProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function processField(FieldInterface $field) {
    parent::processField($field);

    foreach ($field->getValues() as $value) {
      if ($value instanceof TextValueInterface) {
        $value->setProperty('lowercase');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function process(&$value) {
    $value = mb_strtolower($value);
  }

}
