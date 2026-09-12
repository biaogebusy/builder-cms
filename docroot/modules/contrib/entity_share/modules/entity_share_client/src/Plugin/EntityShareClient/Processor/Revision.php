<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Plugin\EntityShareClient\Processor;

use Drupal\entity_share_client\Attribute\ImportProcessor;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_share_client\ImportProcessor\ImportProcessorPluginBase;
use Drupal\entity_share_client\RuntimeImportContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Create new revision for already created entities.
 */
#[ImportProcessor(
  id: 'revision',
  label: new TranslatableMarkup('Revision'),
  description: new TranslatableMarkup('Create new revision.'),
  stages: [
    'prepare_importable_entity_data' => 0,
    'process_entity' => 10,
  ],
)]
class Revision extends ImportProcessorPluginBase implements PluginFormInterface {

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
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

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
    $instance->time = $container->get('datetime.time');
    $instance->stateInformation = $container->get('entity_share_client.state_information');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'enforce_new_revision' => TRUE,
      'translation_affected' => FALSE,
      'revision_log_message' => 'automatic',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['enforce_new_revision'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enforce new revision'),
      '#description' => $this->t('Enforces an entity to be saved as a new revision.'),
      '#default_value' => $this->configuration['enforce_new_revision'],
    ];

    $form['translation_affected'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enforce translation affected'),
      '#description' => $this->t('Not checking this option may cause confusing revision UI when using the Diff module.'),
      '#default_value' => $this->configuration['translation_affected'],
    ];

    $form['revision_log_message'] = [
      '#type' => 'select',
      '#title' => $this->t('Revision log message format'),
      '#description' => $this->t('The log message to use if a new revision is created.'),
      '#options' => [
        'automatic' => $this->t("Automatic message"),
        'user_automatic' => $this->t("Source revision message, followed by automatic message"),
        'automatic_user' => $this->t("Automatic message, followed by source revision message"),
      ],
      '#default_value' => $this->configuration['revision_log_message'],
    ];

    return $form;
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

    // Don't do anything if we don't enforce a new revision.
    if (!$this->configuration['enforce_new_revision']) {
      return;
    }

    $revision_metadata_keys = $processed_entity->getEntityType()->getRevisionMetadataKeys();

    $is_new = $this->entityIsNew[$processed_entity->getEntityTypeId()][$processed_entity->uuid()];

    if (!$is_new) {
      // If the entity already existed, force a new revision.
      $processed_entity->setNewRevision();

      if ($this->configuration['translation_affected']) {
        $processed_entity->setRevisionTranslationAffected(TRUE);
      }
    }

    // In all cases, set the revision time and log message, since either:
    // - The entity is new, and so needs these to be set.
    // - The entity is not new, and we're making a new revision.
    try {
      $revision_metadata_keys = $processed_entity->getEntityType()->getRevisionMetadataKeys();
      if (isset($revision_metadata_keys['revision_created'])) {
        $processed_entity->{$revision_metadata_keys['revision_created']}->value = $this->time->getRequestTime();
      }
      if (isset($revision_metadata_keys['revision_log_message'])) {
        $field_mappings = $runtime_import_context->getFieldMappings();
        $entity_type_id = $processed_entity->getEntityTypeId();
        $entity_bundle = $processed_entity->bundle();

        $incoming_revision_log_message = NULL;
        // @todo Can it ever be the case that this isn't in the field mappings?
        $revision_log_message_public_name = $field_mappings[$entity_type_id][$entity_bundle][$revision_metadata_keys['revision_log_message']] ?? NULL;
        if ($revision_log_message_public_name) {
          $incoming_revision_log_message = $entity_json_data['attributes'][$revision_log_message_public_name] ?? NULL;
        }

        // Construct the log message.
        $revision_log_message = $this->t('Auto created revision during Entity Share synchronization.');

        if ($incoming_revision_log_message) {
          $revision_log_message = match ($this->configuration['revision_log_message']) {
            'user_automatic' => $incoming_revision_log_message . "\n" . $revision_log_message,
            'automatic_user' => $revision_log_message . "\n" . $incoming_revision_log_message,
            default => $revision_log_message,
          };
        }

        $processed_entity->{$revision_metadata_keys['revision_log_message']}->value = $revision_log_message;
      }
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('There was a problem: @message', ['@message' => $e->getMessage()]));
    }
  }

}
