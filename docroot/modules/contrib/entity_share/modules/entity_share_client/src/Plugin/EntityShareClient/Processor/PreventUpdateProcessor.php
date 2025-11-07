<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Plugin\EntityShareClient\Processor;

use Drupal\Core\Language\LanguageInterface;
use Drupal\entity_share_client\ImportProcessor\ImportProcessorPluginBase;
use Drupal\entity_share_client\RuntimeImportContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Prevent update depending on policy.
 *
 * @ImportProcessor(
 *   id = "prevent_update_processor",
 *   label = @Translation("Prevent update processor"),
 *   description = @Translation("Prevent update of an already imported entity if the entity import status has the 'Create only' policy."),
 *   stages = {
 *     "is_entity_importable" = -5,
 *   },
 *   locked = false,
 * )
 */
class PreventUpdateProcessor extends ImportProcessorPluginBase {

  /**
   * The machine name of the create only policy.
   */
  const CREATE_ONLY_POLICY = 'create_only';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Entity import state information service.
   *
   * @var \Drupal\entity_share_client\Service\StateInformationInterface
   */
  protected $stateInformation;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->stateInformation = $container->get('entity_share_client.state_information');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function isEntityImportable(RuntimeImportContext $runtime_import_context, array $entity_json_data) {
    $field_mappings = $runtime_import_context->getFieldMappings();
    $parsed_type = explode('--', $entity_json_data['type']);
    $entity_type_id = $parsed_type[0];
    $entity_bundle = $parsed_type[1];
    // @todo Refactor in attributes to avoid getting entity keys each time.
    $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);
    $entity_keys = $entity_storage->getEntityType()->getKeys();

    $langcode_public_name = FALSE;
    if (!empty($entity_keys['langcode']) && isset($field_mappings[$entity_type_id][$entity_bundle][$entity_keys['langcode']])) {
      $langcode_public_name = $field_mappings[$entity_type_id][$entity_bundle][$entity_keys['langcode']];
    }

    $data_langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED;
    if ($langcode_public_name && !empty($entity_json_data['attributes'][$langcode_public_name])) {
      $data_langcode = $entity_json_data['attributes'][$langcode_public_name];
    }

    // Check if there is an import status and its policy.
    $import_status_entity = $this->stateInformation->getImportStatusByParameters($entity_json_data['id'], $entity_type_id, $data_langcode);
    if ($import_status_entity && $import_status_entity->getPolicy() == self::CREATE_ONLY_POLICY) {
      return FALSE;
    }

    return TRUE;
  }

}
