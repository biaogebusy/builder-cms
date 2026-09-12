<?php

namespace Drupal\commerce_number_pattern;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityType;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the list builder for number patterns.
 */
class NumberPatternListBuilder extends ConfigEntityListBuilder {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity/* , ?CacheableMetadata $cacheability = NULL */) {
    $cacheability = func_num_args() > 1 ? func_get_arg(1) : NULL;
    $operations = parent::getDefaultOperations($entity, $cacheability);
    $access = $entity->access('reset_sequence', NULL, TRUE);
    if ($cacheability instanceof CacheableMetadata) {
      $cacheability->addCacheableDependency($access);
    }

    if ($access->isAllowed() && $entity->hasLinkTemplate('reset-sequence-form')) {
      $operations['reset-sequence'] = [
        'title' => $this->t('Reset sequence'),
        'weight' => 200,
        'url' => $this->ensureDestination($entity->toUrl('reset-sequence-form')),
      ];
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Number pattern');
    $header['id'] = $this->t('ID');
    if ($this->shouldDisplayType()) {
      $header['type'] = $this->t('Type');
    }

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\commerce_number_pattern\Entity\NumberPatternInterface $entity */
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    if ($this->shouldDisplayType()) {
      $target_entity_type_id = $entity->getTargetEntityTypeId();
      $target_entity_type = $this->entityTypeManager->getDefinition($target_entity_type_id);
      $row['type'] = $target_entity_type->getLabel();
    }

    return $row + parent::buildRow($entity);
  }

  /**
   * Checks whether the type column should be displayed.
   *
   * The type column is displayed only if there are multiple possible
   * target entity types.
   *
   * @return bool
   *   TRUE if the type column should be displayed, FALSE otherwise.
   */
  protected function shouldDisplayType() {
    $entity_types = $this->entityTypeManager->getDefinitions();
    $entity_types = array_filter($entity_types, function (EntityType $entity_type) {
      return $entity_type->get('allow_number_patterns');
    });

    return count($entity_types) > 1;
  }

}
