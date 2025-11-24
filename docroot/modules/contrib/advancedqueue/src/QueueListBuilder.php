<?php

namespace Drupal\advancedqueue;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Defines the list builder for queues.
 */
class QueueListBuilder extends ConfigEntityListBuilder {

  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, protected RouterInterface $router) {
    parent::__construct($entity_type, $storage);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('router')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Queue');
    $header['jobs'] = $this->t('Jobs');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\advancedqueue\Entity\QueueInterface $entity */
    $count_labels = [
      Job::STATE_QUEUED => $this->t('Queued'),
      Job::STATE_PROCESSING => $this->t('Processing'),
      Job::STATE_SUCCESS => $this->t('Success'),
      Job::STATE_FAILURE => $this->t('Failure'),
    ];
    $jobs = [];
    foreach ($entity->getBackend()->countJobs() as $state => $count) {
      $jobs[] = sprintf('%s: %s', $count_labels[$state], $count);
    }
    $row['label'] = $entity->label();
    $row['jobs'] = implode(' | ', $jobs);

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    /** @var \Drupal\advancedqueue\Entity\QueueInterface $entity */
    $operations = parent::getDefaultOperations($entity);

    if ($entity->getBackendId() === 'database' && $this->router->getRouteCollection()->get('view.advancedqueue_jobs.page_1')) {
      $operations['jobs'] = [
        'title' => $this->t('List jobs'),
        'weight' => -20,
        'url' => Url::fromRoute('view.advancedqueue_jobs.page_1', ['arg_0' => $entity->id()]),
      ];
    }

    return $operations;
  }

}
