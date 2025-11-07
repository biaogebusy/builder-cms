<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Plugin\EntityShareClient\Processor;

use Drupal\Component\Utility\UrlHelper;
use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_client\RuntimeImportContext;

/**
 * Import embedded entities from text formatted fields.
 *
 * Need to act before the entity is deserialized to ensure the src attribute of
 * img tags will be updated.
 *
 * @ImportProcessor(
 *   id = "embedded_entity_importer",
 *   label = @Translation("Embedded entity"),
 *   description = @Translation("Import embedded entities from text formatted fields. Require the 'Embedded entities (formatted text field only) (Entity Share)' field enhancer enabled on both client and server websites."),
 *   stages = {
 *     "prepare_importable_entity_data" = 20,
 *   },
 *   locked = false,
 * )
 */
class EmbeddedEntityImporter extends EntityReference {

  /**
   * {@inheritdoc}
   */
  public function prepareImportableEntityData(RuntimeImportContext $runtime_import_context, array &$entity_json_data) {
    // Parse entity data to extract urls to get block content from block
    // field. And remove this info.
    if (isset($entity_json_data['attributes']) && is_array($entity_json_data['attributes'])) {
      foreach ($entity_json_data['attributes'] as $field_data) {
        if (is_array($field_data)) {
          if (EntityShareUtility::isNumericArray($field_data)) {
            foreach ($field_data as $value) {
              // Detect formatted text fields.
              if (isset($value['format']) && isset($value['value'])) {
                $this->parseFormattedTextAndImport($runtime_import_context, $value['value']);
              }
            }
          }
          // Detect formatted text fields.
          elseif (isset($field_data['format']) && isset($field_data['value'])) {
            $this->parseFormattedTextAndImport($runtime_import_context, $field_data['value']);
          }
        }
      }
    }
  }

  /**
   * Parse text to import embedded entities.
   *
   * @param \Drupal\entity_share_client\RuntimeImportContext $runtime_import_context
   *   The runtime import context.
   * @param string $text
   *   The formatted text to parse.
   */
  protected function parseFormattedTextAndImport(RuntimeImportContext $runtime_import_context, $text) {
    $matches = [];
    preg_match_all('# data-entity-jsonapi-url="(.*)"#U', $text, $matches);

    foreach ($matches[1] as $url) {
      // Check that the URL is valid.
      if (UrlHelper::isValid($url)) {
        $parsed_url = explode('/', $url);
        if (!empty($parsed_url)) {
          $entity_uuid = array_pop($parsed_url);

          // In the case of embedded entities, if the embedded entity is already
          // present on the website, there is nothing to do.
          if (
            $this->currentRecursionDepth != $this->configuration['max_recursion_depth'] &&
            !$runtime_import_context->isEntityMarkedForImport($entity_uuid)
          ) {
            $runtime_import_context->addEntityMarkedForImport($entity_uuid);
            $this->importUrl($runtime_import_context, $url);
          }
        }
      }
    }
  }

}
