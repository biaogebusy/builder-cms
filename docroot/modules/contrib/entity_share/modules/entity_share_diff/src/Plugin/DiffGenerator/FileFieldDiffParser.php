<?php

declare(strict_types = 1);

namespace Drupal\entity_share_diff\Plugin\DiffGenerator;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_diff\DiffGenerator\DiffGeneratorPluginBase;
use Drupal\file\Entity\File;

/**
 * Plugin to diff file fields.
 *
 * @DiffGenerator(
 *   id = "file_field_diff_parser",
 *   label = @Translation("File Field Diff Parser"),
 *   field_types = {
 *     "file"
 *   },
 * )
 */
class FileFieldDiffParser extends DiffGeneratorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(FieldItemListInterface $field_items, array $remote_field_data = []) {
    $result = [];
    $fileManager = $this->entityTypeManager->getStorage('file');

    // Every item from $field_items is of type FieldItemInterface.
    if (!$this->getRemote()) {
      foreach ($field_items as $field_key => $field_item) {
        // Even though the local field is empty, remote data may be set.
        // So, get field values regardless.
        $values = $field_item->getValue();

        if (!$field_item->isEmpty()) {
          // Compare file names.
          if (isset($values['target_id'])) {
            /** @var \Drupal\file\Entity\File $file */
            $file = $fileManager->load($values['target_id']);
            if ($file instanceof File) {
              $label = (string) $this->t('File name');
              $result[$field_key][$label] = $file->getFilename();
              $label = (string) $this->t('File size');
              $result[$field_key][$label] = $file->getSize();
            }
          }
        }
        // Compare additional (meta) fields.
        foreach ($this->getFieldMetaProperties() as $key => $label) {
          if (isset($values[$key])) {
            $result[$field_key][$label] = $values[$key];
          }
        }
      }
    }
    elseif (!empty($remote_field_data['data'])) {
      $data = [];
      $detailed_response = $this->remoteManager->jsonApiRequest($this->getRemote(), 'GET', $remote_field_data['links']['related']['href']);

      if (!is_null($detailed_response)) {
        $entities_json = Json::decode((string) $detailed_response->getBody());
        if (!empty($entities_json['data'])) {
          $data = EntityShareUtility::prepareData($entities_json['data']);
        }
      }

      foreach (array_keys($remote_field_data['data']) as $field_key) {
        if ($data[$field_key]['attributes']['filename']) {
          $label = (string) $this->t('File name');
          $result[$field_key][$label] = $data[$field_key]['attributes']['filename'];
          $label = (string) $this->t('File size');
          $result[$field_key][$label] = (string) $data[$field_key]['attributes']['filesize'];
        }
        // Compare additional (meta) fields.
        foreach ($this->getFieldMetaProperties() as $key => $label) {
          if (isset($remote_field_data['data'][$field_key]['meta'][$key])) {
            $result[$field_key][$label] = $remote_field_data['data'][$field_key]['meta'][$key];
          }
        }
      }
    }

    return $result;
  }

  /**
   * Declares needed field meta properties.
   */
  protected function getFieldMetaProperties() {
    return [
      'description' => (string) $this->t('Description'),
    ];
  }

}
