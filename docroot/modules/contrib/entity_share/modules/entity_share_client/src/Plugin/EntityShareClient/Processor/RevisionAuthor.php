<?php

declare(strict_types=1);

namespace Drupal\entity_share_client\Plugin\EntityShareClient\Processor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_share_client\Attribute\ImportProcessor;
use Drupal\entity_share_client\ImportProcessor\ImportProcessorPluginBase;
use Drupal\entity_share_client\RuntimeImportContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sets the revision author if the user exists on the client.
 *
 * @todo Consider adding options for what to set the author to, out of anonymous
 * / current user / local copy of remote author.
 */
#[ImportProcessor(
  id: 'revision_author',
  label: new TranslatableMarkup('Revision author (experimental)'),
  description: new TranslatableMarkup("Sets the revision author if the user exists on the client. Must go after the 'revision' processor and before the 'entity_reference' processor."),
  stages: [
    'prepare_importable_entity_data' => 0,
    // Needs to go after the revision processor but before the entity_reference,
    // because EntityReference::processEntity() does a save, which will reset
    // the new revision flag on the entity.
    'process_entity' => 20,
  ],
)]
class RevisionAuthor extends ImportProcessorPluginBase {

  /**
   * Stores whether an entity is being newly created for the pull process.
   *
   * This is not the same as whether it has been pulled already or not: an
   * entity with the same UUID could exist on two sites when databases are
   * copied.
   *
   * @var array
   */
  protected $entityIsNew = [];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.repository'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Creates a RevisionAuthor instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityRepositoryInterface $entityRepository,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function prepareImportableEntityData(RuntimeImportContext $runtime_import_context, array &$entity_json_data) {
    // We need to determine whether the entity is new or not at this stage. We
    // can't rely on isNew() because the import service has already saved it.
    // @todo Remove this if
    // https://www.drupal.org/project/entity_share/issues/3547005 is fixed.
    $parsed_type = explode('--', $entity_json_data['type']);
    $entity_type_id = $parsed_type[0];
    $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);
    $data_uuid = $entity_json_data['id'];

    $existing_entities = $entity_storage->loadByProperties(['uuid' => $data_uuid]);

    $this->entityIsNew[$entity_type_id][$data_uuid] = empty($existing_entities);
  }

  /**
   * {@inheritdoc}
   */
  public function processEntity(RuntimeImportContext $runtime_import_context, ContentEntityInterface $processed_entity, array $entity_json_data) {
    // Don't do anything for an entity that is not revisionable.
    if (!$processed_entity->getEntityType()->isRevisionable()) {
      return;
    }

    $entity_type = $processed_entity->getEntityType();

    // Don't do anything for an entity type that has no revision user field.
    if (!$entity_type->hasRevisionMetadataKey('revision_user')) {
      return;
    }

    $is_new = $this->entityIsNew[$processed_entity->getEntityTypeId()][$processed_entity->uuid()];

    // We only act if the processed entity is being saved as a new revision,
    // or is the first revision.
    if (!($processed_entity->isNewRevision() || $is_new)) {
      return;
    }

    $revision_user_field_name = $entity_type->getRevisionMetadataKey('revision_user');

    $field_mappings = $runtime_import_context->getFieldMappings();
    $entity_type_id = $processed_entity->getEntityTypeId();
    $entity_bundle = $processed_entity->bundle();

    $revision_user_public_name = $field_mappings[$entity_type_id][$entity_bundle][$revision_user_field_name] ?? NULL;

    // Don't do anything if there's no revision user data.
    if (empty($revision_user_public_name)) {
      return;
    }
    if (!isset($entity_json_data['relationships'][$revision_user_public_name]['data']['id'])) {
      return;
    }

    $revision_user_uuid = $entity_json_data['relationships'][$revision_user_public_name]['data']['id'];

    $local_user = $this->entityRepository->loadEntityByUuid('user', $revision_user_uuid);

    // Don't do anything if there's no local version of the revision author.
    if (empty($local_user)) {
      return;
    }

    $processed_entity->setRevisionUserId($local_user->id());
  }

}
