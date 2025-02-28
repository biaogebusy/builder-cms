<?php

namespace Drupal\commerce_promotion;

use Drupal\commerce\CommerceEntityViewsData;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Provides views data for promotions.
 */
class PromotionViewsData extends CommerceEntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // Use "enabled"/"disabled" rather than true/false for the promotion status.
    $data_table = $this->tableMapping->getDataTable();
    $data[$data_table]['status']['filter']['type'] = 'enabled-disabled';
    // Expose the offer as a select list.
    $data[$data_table]['offer__target_plugin_id']['filter']['id'] = 'list_field';
    $data[$data_table]['offer__target_plugin_id']['argument']['id'] = 'string_list_field';

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  protected function processViewsDataForDatetime($table, FieldDefinitionInterface $field_definition, array &$views_field, $field_column_name) {
    parent::processViewsDataForDatetime($table, $field_definition, $views_field, $field_column_name);

    // Promotion date/time fields are always used in the store timezone.
    if ($field_column_name == 'value') {
      $views_field['field']['default_formatter'] = 'commerce_store_datetime';
      $views_field['filter']['id'] = 'commerce_store_datetime';
    }
  }

}
