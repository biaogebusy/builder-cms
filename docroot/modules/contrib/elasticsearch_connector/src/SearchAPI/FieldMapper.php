<?php

namespace Drupal\elasticsearch_connector\SearchAPI;

use Drupal\elasticsearch_connector\Event\FieldMappingEvent;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Builds params for field mapping.
 */
class FieldMapper {

  /**
   * Creates a new Field Mapper.
   *
   * @param \Drupal\search_api\Utility\FieldsHelperInterface $fieldsHelper
   *   The fields helper.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(
    protected FieldsHelperInterface $fieldsHelper,
    protected EventDispatcherInterface $eventDispatcher,
  ) {
  }

  /**
   * Build parameters required to create an index mapping.
   *
   * @param string $indexId
   *   The index ID.
   * @param \Drupal\search_api\IndexInterface $index
   *   Index object.
   *
   * @return array
   *   Parameters required to create an index mapping.
   *
   * @todo We need also:
   *   - $params['index'] - (Required)
   *   - ['type'] - The name of the document type
   *   - ['timeout'] - (time) Explicit operation timeout.
   */
  public function mapFieldParams(string $indexId, IndexInterface $index): array {
    $params = [
      'index' => $indexId,
    ];

    $properties = [
      'id' => [
        'type' => 'keyword',
        'index' => 'true',
      ],
    ];

    // Map index fields.
    $fields = $index->getFields() + $this->getSpecialFields($index);
    foreach ($fields as $field_id => $field_data) {
      $properties[$field_id] = $this->mapFieldProperty($field_data);
    }

    $params['body']['properties'] = $properties;

    return $params;
  }

  /**
   * Gets the list of search API special field names.
   *
   * @return string[]
   *   The list of special field names.
   */
  public function getSpecialFieldNames(): array {
    return [
      'search_api_id',
      'search_api_datasource',
      'search_api_language',
    ];
  }

  /**
   * Creates dummy field objects for the "magic" fields present for every index.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index for which to create the fields. (Needed since field objects
   *   always need an index set.)
   *
   * @return \Drupal\search_api\Item\FieldInterface[]
   *   An array of field objects for all "magic" fields, keyed by field IDs.
   *
   * @see \Drupal\search_api\Backend\BackendPluginBase::getSpecialFields()
   */
  public function getSpecialFields(IndexInterface $index): array {
    $fields = [];
    foreach ($this->getSpecialFieldNames() as $fieldName) {
      $fields[$fieldName] = $this->fieldsHelper->createField($index, $fieldName, ['type' => 'string']);
    }
    return $fields;
  }

  /**
   * Helper function. Get the elasticsearch mapping for a field.
   *
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   The field.
   *
   * @return array
   *   Array of settings.
   */
  protected function mapFieldProperty(FieldInterface $field): array {
    $type = $field->getType();

    $param = match ($type) {
      'text' => [
        'type' => 'text',
        // 'boost' => $field->getBoost(),
        'fields' => [
          'keyword' => [
            'type' => 'keyword',
            'ignore_above' => 256,
          ],
        ],
      ],
      'uri', 'string', 'token' => ['type' => 'keyword'],
      'integer', 'duration' => ['type' => 'integer'],
      'boolean' => ['type' => 'boolean'],
      'decimal' => ['type' => 'float'],
      'date' => [
        'type' => 'date',
        'format' => 'strict_date_optional_time||epoch_second',
      ],
      'attachment' => ['type' => 'attachment'],
      'object' => ['type' => 'nested'],
      'location' => ['type' => 'geo_point'],
      'elasticsearch_connector_rank_feature' => [
        'type' => 'rank_feature',
      ],
      'elasticsearch_connector_date_range' => [
        'type' => 'date_range',
        'format' => 'strict_date_optional_time||epoch_second',
      ],
      'elasticsearch_connector_full_date' => [
        'type' => 'date',
        'format' => 'strict_date_optional_time||epoch_second',
      ],
      default => [],
    };

    // Allow modification of field mapping.
    $event = new FieldMappingEvent($field, $param);
    $this->eventDispatcher->dispatch($event);
    $param = $event->getParam();

    return $param;
  }

}
