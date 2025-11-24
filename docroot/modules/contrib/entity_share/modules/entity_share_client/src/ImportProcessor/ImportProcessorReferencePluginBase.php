<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\ImportProcessor;

use Drupal\Component\Serialization\Json;
use Drupal\entity_share_client\RuntimeImportContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for processors which fetch related entities.
 */
abstract class ImportProcessorReferencePluginBase extends ImportProcessorPluginBase {

  /**
   * The current recursion depth.
   *
   * @var int
   */
  protected $currentRecursionDepth = 0;

  /**
   * The remote manager.
   *
   * @var \Drupal\entity_share_client\Service\RemoteManagerInterface
   */
  protected $remoteManager;

  /**
   * The import service.
   *
   * @var \Drupal\entity_share_client\Service\ImportServiceInterface
   */
  protected $importService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->remoteManager = $container->get('entity_share_client.remote_manager');
    $instance->importService = $container->get('entity_share_client.import_service');
    return $instance;
  }

  /**
   * Helper function.
   *
   * @param \Drupal\entity_share_client\RuntimeImportContext $runtime_import_context
   *   The runtime import context.
   * @param string $url
   *   The URL to import.
   *
   * @return array
   *   The list of entity IDs imported keyed by UUIDs.
   */
  protected function importUrl(RuntimeImportContext $runtime_import_context, $url) {
    $referenced_entities_ids = [];
    $referenced_entities_response = $this->remoteManager->jsonApiRequest($runtime_import_context->getRemote(), 'GET', $url);

    if (is_null($referenced_entities_response)) {
      return $referenced_entities_ids;
    }

    $referenced_entities_json = Json::decode((string) $referenced_entities_response->getBody());

    // $referenced_entities_json['data'] can be null in the case of
    // missing/deleted referenced entities.
    if (!isset($referenced_entities_json['errors']) && !is_null($referenced_entities_json['data'])) {
      $this->currentRecursionDepth++;
      $referenced_entities_ids = $runtime_import_context->getImportService()->importEntityListData($referenced_entities_json['data']);
      $this->currentRecursionDepth--;
    }

    return $referenced_entities_ids;
  }

}

