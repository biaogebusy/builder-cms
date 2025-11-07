<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\entity_share\EntityShareUtility;

/**
 * Class ImportBatchHelper.
 *
 * Contains static method to use for batch operations.
 *
 * @package Drupal\entity_share_client
 */
class ImportBatchHelper {

  /**
   * Batch operation.
   *
   * @param \Drupal\entity_share_client\ImportContext $import_context
   *   The import context.
   * @param string $url
   *   The URL to request.
   * @param array|\ArrayAccess $context
   *   Batch context information.
   */
  public static function importUrlBatch(ImportContext $import_context, $url, &$context) {
    /** @var \Drupal\entity_share_client\Service\ImportServiceInterface $import_service */
    $import_service = \Drupal::service('entity_share_client.import_service');
    $import_prepared = $import_service->prepareImport($import_context);
    if (!$import_prepared) {
      $context['finished'] = 1;
      return;
    }

    if (empty($context['sandbox'])) {
      $response = $import_service->jsonApiRequest('GET', $url);
      $json = Json::decode((string) $response->getBody());
      $entity_list_data = EntityShareUtility::prepareData($json['data']);
      $context['sandbox']['entity_list_data'] = $entity_list_data;

      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = count($entity_list_data);
      $context['sandbox']['batch_size'] = \Drupal::getContainer()->getParameter('entity_share_client.batch_size');
    }
    if (!isset($context['results']['imported_entity_ids'])) {
      $context['results']['imported_entity_ids'] = [];
    }

    $sub_data = array_slice($context['sandbox']['entity_list_data'], $context['sandbox']['progress'], $context['sandbox']['batch_size']);
    $import_service->importEntityListData($sub_data);

    $context['results']['imported_entity_ids'] = NestedArray::mergeDeep($context['results']['imported_entity_ids'], $import_service->getRuntimeImportContext()->getImportedEntities());
    $context['sandbox']['progress'] += count($sub_data);
    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }

  /**
   * Batch finish callback.
   *
   * @param bool $success
   *   A boolean indicating whether the batch has completed successfully.
   * @param array $results
   *   The value set in $context['results'] by callback_batch_operation().
   * @param array $operations
   *   If $success is FALSE, contains the operations that remained unprocessed.
   */
  public static function importUrlBatchFinished($success, array $results, array $operations) {
    if ($success) {
      $language_manager = \Drupal::languageManager();
      $total = 0;

      // Count for each language.
      // Currently not possible to have details like entity type or bundle.
      // This would require a rework on how imported entities tracking work.
      foreach ($results['imported_entity_ids'] as $langcode => $entity_uuids) {
        $language = $language_manager->getLanguage($langcode);
        $language_count = count($entity_uuids);
        $total += $language_count;
        $message = new PluralTranslatableMarkup(
          $language_count,
          'One entity imported in @language_label.',
          '@count entities imported in @language_label.',
          [
            '@language_label' => $language->getName(),
          ]
        );
        \Drupal::messenger()->addStatus($message);
      }

      $message = new PluralTranslatableMarkup(
        $total,
        'One entity imported in total.',
        '@count entities imported in total.'
      );
      \Drupal::messenger()->addStatus($message);
    }
    else {
      $message = t('Finished with an error.');
      \Drupal::messenger()->addError($message);
    }
  }

}
