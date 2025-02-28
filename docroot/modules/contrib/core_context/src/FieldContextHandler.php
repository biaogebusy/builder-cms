<?php

namespace Drupal\core_context;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\ctools\ContextMapperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Exposes contexts stored on an entity field.
 */
final class FieldContextHandler implements EntityContextHandlerInterface {

  use CacheableContextTrait;

  /**
   * The context mapper service.
   *
   * @var \Drupal\ctools\ContextMapperInterface
   */
  private $contextMapper;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  private $entityFieldManager;

  /**
   * FieldContextHandler constructor.
   *
   * @param \Drupal\ctools\ContextMapperInterface $context_mapper
   *   The context mapper service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   */
  public function __construct(ContextMapperInterface $context_mapper, EntityFieldManagerInterface $entity_field_manager) {
    $this->contextMapper = $context_mapper;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $container->get('ctools.context_mapper'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getContexts(EntityInterface $entity) {
    assert($entity instanceof FieldableEntityInterface);

    $entity_type_id = $entity->getEntityTypeId();
    $field_map = $this->entityFieldManager->getFieldMapByFieldType('context');

    if (empty($field_map[$entity_type_id])) {
      return [];
    }

    $field_name = key($field_map[$entity_type_id]);
    $contexts = [];

    if ($entity->hasField($field_name) === FALSE) {
      return $contexts;
    }

    $items = $entity->get($field_name);
    if ($items->isEmpty()) {
      return $contexts;
    }

    /** @var \Drupal\core_context\Plugin\Field\FieldType\ContextItem $item */
    foreach ($items as $item) {
      $contexts[$item->id] = $item->getValue();
    }
    $contexts = $this->contextMapper->getContextValues($contexts);
    return $this->applyCaching($contexts, $entity);
  }

}
