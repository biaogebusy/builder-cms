<?php

namespace Drupal\entity\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derives local tasks for entity types.
 */
class EntityTasksDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs an entity local tasks deriver.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    if (!$this->derivatives) {
      foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
        $handlers = $entity_type->getHandlerClasses();
        if (isset($handlers['local_task_provider'])) {
          foreach ($handlers['local_task_provider'] as $class) {
            /** @var \Drupal\entity\Menu\EntityLocalTaskProviderInterface $handler */
            $handler = $this->entityTypeManager->createHandlerInstance($class, $entity_type);
            $this->derivatives += $handler->buildLocalTasks($entity_type);
          }
        }
      }
    }
    return $this->derivatives;
  }

}
