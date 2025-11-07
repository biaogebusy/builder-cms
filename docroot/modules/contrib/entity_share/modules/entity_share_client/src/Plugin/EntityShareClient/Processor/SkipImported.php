<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Plugin\EntityShareClient\Processor;

use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_client\ImportProcessor\ImportProcessorPluginBase;
use Drupal\entity_share_client\RuntimeImportContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Skip already imported entities.
 *
 * @ImportProcessor(
 *   id = "skip_imported",
 *   label = @Translation("Skip already imported entities"),
 *   description = @Translation("If the entities haven't changed on Remote, this plugin prevents saving of the entities."),
 *   stages = {
 *     "is_entity_importable" = -5,
 *   },
 * )
 */
class SkipImported extends ImportProcessorPluginBase {

  /**
   * The Entity import state information service.
   *
   * @var \Drupal\entity_share_client\Service\StateInformationInterface
   */
  protected $stateInformation;

  /**
   * The resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->stateInformation = $container->get('entity_share_client.state_information');
    $instance->resourceTypeRepository = $container->get('jsonapi.resource_type.repository');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function isEntityImportable(RuntimeImportContext $runtime_import_context, array $entity_data) {
    $entity_uuid = $entity_data['id'];
    $parsed_type = explode('--', $entity_data['type']);
    $entity_type_id = $parsed_type[0];
    $resource_type = $this->resourceTypeRepository->get(
      $parsed_type[0],
      $parsed_type[1]
    );
    // Determine public name of 'langcode'.
    $langcode_public_name = FALSE;
    if ($resource_type->hasField('langcode')) {
      $langcode_public_name = $resource_type->getPublicName('langcode');
    }
    $langcode = $entity_data['attributes'][$langcode_public_name] ?? NULL;
    // Get the information of the last import of this entity.
    $import_status_entity = $this->stateInformation->getImportStatusByParameters($entity_uuid, $entity_type_id, $langcode);
    // If there is no information on when this entity was last imported, it
    // means that probably it hasn't been - so it should be importable.
    if (!$import_status_entity) {
      return TRUE;
    }
    // Determine public name of 'changed' property.
    $changed_public_name = FALSE;
    if ($resource_type->hasField('changed')) {
      $changed_public_name = $resource_type->getPublicName('changed');
    }
    // Get the remote 'changed' value, if exists:
    $remote_changed_time = $entity_data['attributes'][$changed_public_name] ?? FALSE;
    // If there is no information on when the remote entity has been modified,
    // we shouldn't skip it - so it should be importable.
    if ($remote_changed_time === FALSE) {
      return TRUE;
    }
    $remote_changed_timestamp = EntityShareUtility::convertChangedTime($remote_changed_time);
    // If the remote entity was modified after our last import, it should
    // be importable.
    return $import_status_entity->getLastImport() < $remote_changed_timestamp;
  }

}
