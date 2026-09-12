<?php

namespace Drupal\commerce_price\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\views\FieldViewsDataProvider;

/**
 * Views hook implementations for Commerce Price.
 */
class CommercePriceViewsHooks {

  use StringTranslationTrait;

  /**
   * Constructs a new CommercePriceViewsHooks object.
   *
   * @param \Drupal\views\FieldViewsDataProvider|null $fieldViewsDataProvider
   *   The field views data provider.
   */
  public function __construct(
    protected readonly ?FieldViewsDataProvider $fieldViewsDataProvider = NULL,
  ) {
  }

  /**
   * Implements hook_field_views_data().
   *
   * Views integration for price fields.
   */
  #[Hook('field_views_data')]
  public function fieldViewsData(FieldStorageConfigInterface $field_storage): array {
    if ($this->fieldViewsDataProvider) {
      $data = $this->fieldViewsDataProvider->defaultFieldImplementation($field_storage);
    }
    else {
      $deprecated_function = 'views_field_default_views_data';
      $data = $deprecated_function($field_storage);
    }
    $field_name = $field_storage->getName();
    foreach ($data as $table_name => $table_data) {
      if (isset($table_data[$field_name])) {
        $data[$table_name][$field_name . '_number']['field'] = [
          'id' => 'numeric',
          'field_name' => $table_data[$field_name]['field']['field_name'],
          'entity_type' => $table_data[$field_name]['field']['entity_type'],
          'label' => $this->t('number from @field_name', ['@field_name' => $field_name]),
        ];
        $data[$table_name][$field_name . '_currency_code']['field'] = [
          'id' => 'standard',
          'field_name' => $table_data[$field_name]['field']['field_name'],
          'entity_type' => $table_data[$field_name]['field']['entity_type'],
          'label' => $this->t('currency from @field_name', ['@field_name' => $field_name]),
        ];
        $data[$table_name][$field_name . '_currency_code']['filter']['id'] = 'commerce_currency';
      }
    }

    return $data;
  }

}
