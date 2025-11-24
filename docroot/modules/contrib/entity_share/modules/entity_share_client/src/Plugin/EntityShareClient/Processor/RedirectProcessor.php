<?php

namespace Drupal\entity_share_client\Plugin\EntityShareClient\Processor;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_share_client\ImportProcessor\ImportProcessorPluginBase;
use Drupal\entity_share_client\RuntimeImportContext;
use Drupal\entity_share_client\Service\RemoteManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Pulls redirect entities which point to the processed entity.
 *
 * This requires the user authenticated on the server to have access to view
 * redirect entities. Rather than grant the admin permission to manage redirect
 * entities, we recommend the patch at
 * https://www.drupal.org/project/redirect/issues/3057679 which adds more
 * granular permissions.
 *
 * This uses the 'prepare_entity_data' stage rather than
 * 'prepare_importable_entity_data' stage, to guarantee this runs before the
 * default_data_processor plugin, as that removes the remote ID from the remote
 * entity JSONAPI data. This means that $this->remoteIds may hold remote IDs for
 * entities that get discarded in the 'is_entity_importable' stage.
 *
 * @ImportProcessor(
 *   id = "redirect_processor",
 *   label = @Translation("Redirect processor"),
 *   description = @Translation("Pulls redirect entities which point to a pulled entity. Requires Redirect module. The client authorization needs to have access to view redirect entities on the server."),
 *   stages = {
 *     "prepare_entity_data" = -200,
 *     "process_entity" = 10,
 *   },
 *   locked = false,
 * )
 */
class RedirectProcessor extends ImportProcessorPluginBase {

  /**
   * Stores the remote entity IDs between stages.
   *
   * A nested array keyed successively by entity type ID then entity UUID. The
   * value is the remote entity ID.
   *
   * @var array
   */
  protected $remoteIds;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The remote manager.
   *
   * @var \Drupal\entity_share_client\Service\RemoteManagerInterface
   */
  protected $remoteManager;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_share_client.remote_manager'),
      $container->get('logger.channel.entity_share_client'),
    );
  }

  /**
   * Creates a RedirectProcessor instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\entity_share_client\Service\RemoteManagerInterface $remote_manager
   *   The remote manager.
   * @param \Psr\Log\LoggerInterface
   *   The logger.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    RemoteManagerInterface $remote_manager,
    LoggerInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->remoteManager = $remote_manager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareEntityData(RuntimeImportContext $runtime_import_context, array &$entity_json_data) {
    $field_mappings = $runtime_import_context->getFieldMappings();
    [$entity_type_id, $entity_bundle] = explode('--', $entity_json_data['type']);

    $id_field_name = $this->entityTypeManager->getDefinition($entity_type_id)->getKey('id');
    $id_public_name = $field_mappings[$entity_type_id][$entity_bundle][$id_field_name];
    $remote_id = $entity_json_data['attributes'][$id_public_name];

    $uuid = $entity_json_data['id'];

    $this->remoteIds[$entity_type_id][$uuid] = $remote_id;
  }

  /**
   * {@inheritdoc}
   */
  public function processEntity(RuntimeImportContext $runtime_import_context, ContentEntityInterface $processed_entity, array $entity_json_data) {
    $entity_type_id = $processed_entity->getEntityTypeId();

    // Do nothing if the entity is itself a redirect.
    if ($entity_type_id == 'redirect') {
      return;
    }

    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);

    // Do nothing if the entity doesn't have a canonical link template.
    if (!$entity_type->hasLinkTemplate('canonical')) {
      return;
    }

    $uuid = $processed_entity->uuid();
    $remote_id = $this->remoteIds[$entity_type_id][$uuid];

    $remote = $runtime_import_context->getRemote();
    $remote_url = $remote->get('url');

    // Make a dummy entity with the remote ID to get its canonical path as if it
    // were an entity on the client. The entity ID and bundle should suffice
    // for most entity types.
    $dummy_entity_data = [
      $entity_type->getKey('id') => $remote_id,
    ];
    if ($bundle_field_name = $entity_type->getKey('bundle')) {
      $dummy_entity_data[$bundle_field_name] = $processed_entity->bundle();
    }
    $dummy_entity = $this->entityTypeManager->getStorage($entity_type_id)->create($dummy_entity_data);

    $remote_canonical_path = $dummy_entity->toUrl('canonical')->toString();

    // Query for redirects with both the 'internal:' and 'entity:' URI schema,
    // as redirects can use either (see
    // https://www.drupal.org/project/redirect/issues/3534885).
    $remote_internal_uri = 'internal:/' . ltrim($remote_canonical_path, '/');
    $remote_entity_uri = 'entity:' . $entity_type_id . '/' . $remote_id;

    // Form a JSONAPI query URL to get redirect entities that point to the
    // internal path of the current entity.
    $filters = [];

    // Query for either an internal: uri or an entity: uri.
    $filters['redirect-group']['group']['conjunction'] = 'OR';

    $filters['entity-uri']['condition']['memberOf'] = 'redirect-group';
    $filters['entity-uri']['condition']['path'] = 'redirect_redirect.uri';
    $filters['entity-uri']['condition']['operator'] = '=';
    $filters['entity-uri']['condition']['value'] = $remote_entity_uri;

    $filters['internal-uri']['condition']['memberOf'] = 'redirect-group';
    $filters['internal-uri']['condition']['path'] = 'redirect_redirect.uri';
    $filters['internal-uri']['condition']['operator'] = '=';
    $filters['internal-uri']['condition']['value'] = $remote_internal_uri;

    // Query for either the same language as the pulled entity, or an undefined
    // language.
    $filters['language-group']['group']['conjunction'] = 'OR';

    $filters['entity-language']['condition']['memberOf'] = 'language-group';
    $filters['entity-language']['condition']['path'] = 'language';
    $filters['entity-language']['condition']['operator'] = '=';
    $filters['entity-language']['condition']['value'] = $processed_entity->language()->getId();

    $filters['und-language']['condition']['memberOf'] = 'language-group';
    $filters['und-language']['condition']['path'] = 'language';
    $filters['und-language']['condition']['operator'] = '=';
    $filters['und-language']['condition']['value'] = 'und';

    $redirect_jsonapi_url = Url::fromUri(
      $remote_url . '/jsonapi/redirect/redirect',
      [
        'query' => [
          'filter' => $filters,
        ],
      ],
    )->toUriString();

    $redirect_entities_response = $this->remoteManager->jsonApiRequest($runtime_import_context->getRemote(), 'GET', $redirect_jsonapi_url);

    $redirect_entities_json = Json::decode((string) $redirect_entities_response->getBody());

    // Bail if the request produced an error.
    if (isset($redirect_entities_json['errors']) && empty($redirect_entities_json['data'])) {
      $this->logger->warning("Errors in JSONAPI request for redirect entities with url :url.", [
        ':url' => $redirect_jsonapi_url,
      ]);

      return;
    }

    $redirect_entities_json_data = $redirect_entities_json['data'];

    // Replace the remote internal URI with the local one. The processed entity
    // already has an ID, because it has either been loaded or already been
    // saved by ImportService::getProcessedEntity().
    $local_internal_uri = 'internal:/' . ltrim($processed_entity->toUrl('canonical')->toString(), '/');
    foreach ($redirect_entities_json_data as &$entity_data) {
      $entity_data['attributes']['redirect_redirect']['uri'] = $local_internal_uri;
    }

    $runtime_import_context->getImportService()->importEntityListData($redirect_entities_json_data);
  }

}
