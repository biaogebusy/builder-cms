<?php

namespace Drupal\entity_share_client\Plugin\EntityShareClient\Processor;

use Drupal\entity_share_client\Attribute\ImportProcessor;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_share_client\ImportProcessor\ImportProcessorPluginBase;
use Drupal\entity_share_client\RuntimeImportContext;
use Drupal\entity_share_client\Service\ImportServiceInterface;
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
 * default_data_processor and path_alias_processor plugins, as those remove the
 * remote ID and path alias respectively from the remote entity JSONAPI data.
 * This means that $this->remoteIds and $this->pathAliases may hold data for
 * entities that get discarded in the 'is_entity_importable' stage.
 */
#[ImportProcessor(
  id: 'redirect_processor',
  label: new TranslatableMarkup('Redirect processor'),
  description: new TranslatableMarkup('Pulls redirect entities which point to a pulled entity. Requires Redirect module. The client authorization needs to have access to view redirect entities on the server. The Redirect hash filter should also be used.'),
  stages: [
    'prepare_entity_data' => -200,
    'process_entity' => 10,
  ],
)]
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
   * Stores the path aliases for remote entities between stages.
   *
   * A nested array keyed successively by entity type ID, entity UUID, and
   * entity langcode. The value is the path alias string.
   *
   * @var array
   */
  protected $pathAliases;

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
   * The import service.
   *
   * @var \Drupal\entity_share_client\Service\ImportServiceInterface
   */
  protected $importService;

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
      $container->get('entity_share_client.import_service'),
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
   * @param \Drupal\entity_share_client\Service\ImportServiceInterface $import_service
   *   The import service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    RemoteManagerInterface $remote_manager,
    LoggerInterface $logger,
    ImportServiceInterface $import_service,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->remoteManager = $remote_manager;
    $this->logger = $logger;
    $this->importService = $import_service;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareEntityData(RuntimeImportContext $runtime_import_context, array &$entity_json_data) {
    $field_mappings = $runtime_import_context->getFieldMappings();
    [$entity_type_id, $entity_bundle] = explode('--', $entity_json_data['type']);
    $uuid = $entity_json_data['id'];

    // Store the remote ID for the process stage.
    $id_field_name = $this->entityTypeManager->getDefinition($entity_type_id)->getKey('id');
    $id_public_name = $field_mappings[$entity_type_id][$entity_bundle][$id_field_name];
    $remote_id = $entity_json_data['attributes'][$id_public_name];

    $this->remoteIds[$entity_type_id][$uuid] = $remote_id;

    // Store the path alias, if there is one, for the process stage.
    if (isset($field_mappings[$entity_type_id][$entity_bundle]['path'])) {
      $path_public_name = $field_mappings[$entity_type_id][$entity_bundle]['path'];
      if (isset($entity_json_data['attributes'][$path_public_name]['alias'])) {
        $path_alias = $entity_json_data['attributes'][$path_public_name]['alias'];
        $langcode = $entity_json_data['attributes']['langcode'];

        $this->pathAliases[$entity_type_id][$uuid][$langcode] = $path_alias;
      }
    }
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
    $langcode = $processed_entity->language()->getId();
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

    // We need to set the 'alias' option to something non-empty to prevent
    // \Drupal\path_alias\PathProcessor\AliasPathProcessor from replacing the
    // canonical URL with an alias.
    // @see https://www.drupal.org/project/drupal/issues/3547430
    // @todo Consider checking what we get starts with the entity type ID, and
    // logging a warning if not, in case anything else mucks around with it.
    $remote_canonical_path = $dummy_entity->toUrl('canonical', ['alias' => TRUE])->toString();

    // Query for redirects with both the 'internal:' and 'entity:' URI schema,
    // as redirects can use either (see
    // https://www.drupal.org/project/redirect/issues/3534885).
    $remote_internal_uri = 'internal:' . $remote_canonical_path;
    $remote_entity_uri = 'entity:' . $entity_type_id . '/' . $remote_id;

    // Form a JSONAPI query URL to get redirect entities that point to the
    // internal path of the current entity.
    $filters = [];

    // Query for either:
    // - an internal: uri
    // - an entity: uri
    // - a path alias the entity has one
    $filters['redirect-group']['group']['conjunction'] = 'OR';

    $filters['entity-uri']['condition']['memberOf'] = 'redirect-group';
    $filters['entity-uri']['condition']['path'] = 'redirect_redirect.uri';
    $filters['entity-uri']['condition']['operator'] = '=';
    $filters['entity-uri']['condition']['value'] = $remote_entity_uri;

    $filters['internal-uri']['condition']['memberOf'] = 'redirect-group';
    $filters['internal-uri']['condition']['path'] = 'redirect_redirect.uri';
    $filters['internal-uri']['condition']['operator'] = '=';
    $filters['internal-uri']['condition']['value'] = $remote_internal_uri;

    if (isset($this->pathAliases[$entity_type_id][$uuid][$langcode])) {
      $filters['alias']['condition']['memberOf'] = 'redirect-group';
      $filters['alias']['condition']['path'] = 'redirect_redirect.uri';
      $filters['alias']['condition']['operator'] = '=';
      $filters['alias']['condition']['value'] = 'internal:' . $this->pathAliases[$entity_type_id][$uuid][$langcode];
    }

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
    $filters['und-language']['condition']['value'] = LanguageInterface::LANGCODE_NOT_SPECIFIED;

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

    // Replace the remote internal URI with the local one, if it includes an
    // entity ID. The processed entity already has an ID, because it has either
    // been loaded or already been saved by ImportService::getProcessedEntity().
    $local_internal_uri = 'internal:' . $processed_entity->toUrl('canonical', ['alias' => TRUE])->toString();
    $local_entity_uri = 'entity:' . $entity_type_id .'/' . $processed_entity->id();
    foreach ($redirect_entities_json_data as &$entity_data) {
      $local_redirect_uri = match($entity_data['attributes']['redirect_redirect']['uri']) {
        $remote_internal_uri => $local_internal_uri,
        $remote_entity_uri => $local_entity_uri,
        default => NULL,
      };

      if ($local_redirect_uri) {
        $entity_data['attributes']['redirect_redirect']['uri'] = $local_redirect_uri;
      }
    }

    $this->importService->importEntityListData($redirect_entities_json_data);
  }

}
