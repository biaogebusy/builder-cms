<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Plugin\EntityShareClient\Processor;

use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_client\RuntimeImportContext;

/**
 * Import internal content from Link fields.
 *
 * Act in prepare_importable_entity_data stage and not process_entity stage to
 * be effective before entity denormalization.
 *
 * @ImportProcessor(
 *   id = "link_internal_content_importer",
 *   label = @Translation("Link internal content"),
 *   description = @Translation("Import internal content from link fields. Require the 'UUID for link (link field only) (Entity Share)' field enhancer enabled on both client and server websites."),
 *   stages = {
 *     "prepare_importable_entity_data" = 20,
 *   },
 *   locked = false,
 * )
 */
class LinkInternalContentImporter extends EntityReference {

  /**
   * {@inheritdoc}
   */
  public function prepareImportableEntityData(RuntimeImportContext $runtime_import_context, array &$entity_json_data) {
    if (isset($entity_json_data['attributes']) && is_array($entity_json_data['attributes'])) {
      foreach ($entity_json_data['attributes'] as $field_name => $field_data) {
        if (is_array($field_data)) {
          if (EntityShareUtility::isNumericArray($field_data)) {
            foreach ($field_data as $delta => $value) {
              if (isset($value['content_entity_href'])) {
                $entity_json_data['attributes'][$field_name][$delta]['uri'] = $this->processLink($runtime_import_context, $value['uri'], $value['content_entity_href']);
              }
            }
          }
          elseif (isset($field_data['content_entity_href'])) {
            $entity_json_data['attributes'][$field_name]['uri'] = $this->processLink($runtime_import_context, $field_data['uri'], $field_data['content_entity_href']);
          }
        }
      }
    }
  }

  /**
   * Attempts to import UUID-enhanced link content.
   *
   * @param \Drupal\entity_share_client\RuntimeImportContext $runtime_import_context
   *   The import context.
   * @param string $uri
   *   URI should be in the format entity:[entity_type]/[bundle_name]/[UUID].
   * @param string $import_url
   *   The import URL prepared by the entity_share_uuid_link enhancer plugin.
   *
   * @return string
   *   Link to the imported content in the form entity:[entity_type]/[Id].
   */
  protected function processLink(RuntimeImportContext $runtime_import_context, string $uri, string $import_url) {
    // Check if it is a link to an entity.
    preg_match("/entity:(.*)\/(.*)\/(.*)/", $uri, $parsed_uri);
    // If the link is not in UUID enhanced format, just return the original URI.
    if (empty($parsed_uri)) {
      return $uri;
    }

    $entity_type = $parsed_uri[1];
    $entity_uuid = $parsed_uri[3];

    // In the case of links, if the linked entity is already
    // present on the website, there is nothing to do as the
    // UuidLinkEnhancer::doTransform() method will convert the URI format back
    // to Drupal expected format.
    if (
      $this->currentRecursionDepth != $this->configuration['max_recursion_depth'] &&
      !$runtime_import_context->isEntityMarkedForImport($entity_uuid)
    ) {
      $runtime_import_context->addEntityMarkedForImport($entity_uuid);
      $referenced_entities_ids = $this->importUrl($runtime_import_context, $import_url);
      if (!empty($referenced_entities_ids) && isset($referenced_entities_ids[$entity_uuid])) {
        return 'entity:' . $entity_type . '/' . $referenced_entities_ids[$entity_uuid];
      }
    }
    else {
      return $uri;
    }
  }

}
