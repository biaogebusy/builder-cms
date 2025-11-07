<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Plugin\EntityShareClient\Processor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\entity_share_client\ImportProcessor\ImportProcessorPluginBase;
use Drupal\entity_share_client\RuntimeImportContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Create new revision for already created entities.
 *
 * @ImportProcessor(
 *   id = "revision",
 *   label = @Translation("Revision"),
 *   description = @Translation("Create new revision."),
 *   stages = {
 *     "process_entity" = 10,
 *   },
 *   locked = false,
 * )
 */
class Revision extends ImportProcessorPluginBase implements PluginFormInterface {

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
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function processEntity(RuntimeImportContext $runtime_import_context, ContentEntityInterface $processed_entity, array $entity_json_data) {
    $import_status_entity = $this->stateInformation->getImportStatusOfEntity($processed_entity);
    if ($processed_entity->getEntityType()->isRevisionable()
      && $this->configuration['enforce_new_revision']
      && $import_status_entity
    ) {

      $processed_entity->setNewRevision();
      if ($this->configuration['translation_affected']) {
        $processed_entity->setRevisionTranslationAffected(TRUE);
      }

      try {
        $revision_metadata_keys = $processed_entity->getEntityType()->getRevisionMetadataKeys();
        if (isset($revision_metadata_keys['revision_created'])) {
          $processed_entity->{$revision_metadata_keys['revision_created']}->value = $this->time->getRequestTime();
        }
        if (isset($revision_metadata_keys['revision_log_message'])) {
          $processed_entity->{$revision_metadata_keys['revision_log_message']}->value = $this->t('Auto created revision during Entity Share synchronization.');
        }
      }
      catch (\Exception $e) {
        $this->messenger()->addError($this->t('There was a problem: @message', ['@message' => $e->getMessage()]));
      }
    }
  }

}
