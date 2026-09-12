<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorProcess;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorProcessRule;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorFieldProcessInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The queue processor.
 */
#[AiAutomatorProcessRule(
  id: 'queue',
  title: new TranslatableMarkup('Queue/Cron'),
  description: new TranslatableMarkup('Saves as a queue worker and runs on cron.'),
)]
class QueueWorkerProcessor implements AiAutomatorFieldProcessInterface, ContainerFactoryPluginInterface {

  /**
   * A queue factory.
   */
  protected QueueFactory $queueFactory;

  /**
   * The database connection.
   */
  protected Connection $connection;

  /**
   * Constructor.
   */
  final public function __construct(QueueFactory $queueFactory, Connection $connection) {
    $this->queueFactory = $queueFactory;
    $this->connection = $connection;
  }

  /**
   * {@inheritDoc}
   */
  final public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('queue'),
      $container->get('database'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function modify(EntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    $allowRequeue = $automatorConfig['queue_allow_requeue'] ?? FALSE;

    if (!$allowRequeue && $this->isAlreadyQueued($entity, $fieldDefinition)) {
      return TRUE;
    }

    $queue = $this->queueFactory->get('ai_automator_field_modifier');
    $queue->createItem([
      'entity_id' => $entity->id(),
      'entity_type' => $entity->getEntityTypeId(),
      'fieldDefinition' => $fieldDefinition,
      'automatorConfig' => $automatorConfig,
    ]);
    return TRUE;
  }

  /**
   * Checks whether a queue item for this entity/field combination exists.
   */
  protected function isAlreadyQueued(EntityInterface $entity, FieldDefinitionInterface $fieldDefinition): bool {
    try {
      $result = $this->connection->select('queue', 'q')
        ->fields('q', ['data'])
        ->condition('name', 'ai_automator_field_modifier')
        ->execute();
    }
    catch (\Exception $e) {
      // If the queue table does not exist yet, no items can be pending.
      if ($this->connection->schema()->tableExists('queue')) {
        throw $e;
      }
      return FALSE;
    }

    foreach ($result as $record) {
      $data = unserialize($record->data, ['allowed_classes' => FALSE]);
      if (($data['entity_type'] ?? NULL) === $entity->getEntityTypeId()
        && ($data['entity_id'] ?? NULL) == $entity->id()
        && ($data['automatorConfig']['field_name'] ?? NULL) === $fieldDefinition->getName()) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function preProcessing(EntityInterface $entity) {
  }

  /**
   * {@inheritDoc}
   */
  public function postProcessing(EntityInterface $entity) {
  }

  /**
   * Should run on import.
   */
  public function isImport() {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function processorIsAllowed(EntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    return TRUE;
  }

}
