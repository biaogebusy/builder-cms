<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Plugin\EntityShareClient\Processor;

use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_client\RuntimeImportContext;

/**
 * Import block contents from block fields.
 *
 * @ImportProcessor(
 *   id = "block_field_block_content_importer",
 *   label = @Translation("Block field block content"),
 *   description = @Translation("Import block contents from block fields. Require the 'Block field (Block field only) (Entity Share)' field enhancer enabled on both client and server websites."),
 *   stages = {
 *     "prepare_importable_entity_data" = 20,
 *   },
 *   locked = false,
 * )
 */
class BlockFieldBlockContentImporter extends EntityReference {

  /**
   * {@inheritdoc}
   */
  public function prepareImportableEntityData(RuntimeImportContext $runtime_import_context, array &$entity_json_data) {
    // Parse entity data to extract urls to get block content from block
    // field. And remove this info.
    if (isset($entity_json_data['attributes']) && is_array($entity_json_data['attributes'])) {
      foreach ($entity_json_data['attributes'] as $field_name => $field_data) {
        if (is_array($field_data)) {
          if (EntityShareUtility::isNumericArray($field_data)) {
            foreach ($field_data as $delta => $value) {
              if (isset($value['block_content_href'])) {
                $this->processBlockContent($runtime_import_context, $value['block_content_href']);
                unset($entity_json_data['attributes'][$field_name][$delta]['block_content_href']);
              }
            }
          }
          elseif (isset($field_data['block_content_href'])) {
            $this->processBlockContent($runtime_import_context, $field_data['block_content_href']);
            unset($entity_json_data['attributes'][$field_name]['block_content_href']);
          }
        }
      }
    }
  }

  /**
   * Attempts to import a block content.
   *
   * @param \Drupal\entity_share_client\RuntimeImportContext $runtime_import_context
   *   The import context.
   * @param string $import_url
   *   The import URL prepared by the entity_share_block_field enhancer plugin.
   */
  protected function processBlockContent(RuntimeImportContext $runtime_import_context, string $import_url) {
    $parsed_url = explode('/', $import_url);
    if (!empty($parsed_url)) {
      $entity_uuid = array_pop($parsed_url);

      // In the case of block content entities, if the block content entity is
      // already present on the website, there is nothing to do.
      if (
        $this->currentRecursionDepth != $this->configuration['max_recursion_depth'] &&
        !$runtime_import_context->isEntityMarkedForImport($entity_uuid)
      ) {
        $runtime_import_context->addEntityMarkedForImport($entity_uuid);
        $this->importUrl($runtime_import_context, $import_url);
      }
    }
  }

}
