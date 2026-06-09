<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorProcess;

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
   * Constructor.
   */
  final public function __construct(QueueFactory $queueFactory) {
    $this->queueFactory = $queueFactory;
  }

  /**
   * {@inheritDoc}
   */
  final public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('queue'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function modify(EntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
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
