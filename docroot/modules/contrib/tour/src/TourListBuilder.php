<?php

namespace Drupal\tour;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of tours.
 */
class TourListBuilder extends EntityListBuilder {

  /**
   * Constructs a new TourListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    protected AccountProxyInterface $currentUser,
  ) {
    parent::__construct($entity_type, $storage);

  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    $entity_type_manager = $container->get('entity_type.manager');
    return new static(
      $entity_type,
      $entity_type_manager->getStorage($entity_type->id()),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $row['tour_name'] = $this->t('Tour name');
    $row['machine_name'] = $this->t('Machine name');
    $row['status'] = $this->t('Status');
    $row['tips'] = $this->t('Number of tips');
    $row['operations'] = $this->t('Operations');
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function load(): array {
    $entities = [
      'enabled' => [],
      'disabled' => [],
    ];
    foreach (parent::load() as $entity) {
      if ($entity->status()) {
        $entities['enabled'][] = $entity;
      }
      else {
        $entities['disabled'][] = $entity;
      }
    }
    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    $row['title'] = [
      'data' => $entity->label(),
      'class' => ['menu-label'],
    ];

    $row = parent::buildRow($entity);

    $data['tour_name'] = Html::escape($entity->label());
    $data['machine_name'] = Html::escape($entity->id());
    $data['status'] = Html::escape($entity->status() ? 'Enabled' : 'Disabled');

    // Count the number of tips.
    $data['tips'] = count($entity->getTips());
    $data['operations'] = $row['operations'];
    // Wrap the whole row so that the entity ID is used as a class.
    return [
      'data' => $data,
      'attributes' => [
        'class' => [
          $entity->id(),
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity): array {
    $operations = parent::getOperations($entity);

    $operations['edit'] = [
      'title' => $this->t('Edit'),
      'url' => $entity->toUrl('edit-form'),
      'attributes' => [
        'aria-label' => $this->t('Edit @tour', ['@tour' => $entity->label()]),
        'data-drupal-selector' => 'tour-listing-' . $entity->id(),
      ],
      'weight' => 1,
    ];

    if ($entity->status()) {
      $operations['disable'] = [
        'title' => $this->t('Disable'),
        'url' => Url::fromRoute('entity.tour.disable', ['tour' => $entity->id()]),
        'attributes' => [
          'class' => ['use-ajax'],
          'aria-label' => $this->t('Disable @tour', ['@tour' => $entity->label()]),
          'data-drupal-selector' => 'tour-listing-' . $entity->id(),
        ],
        'weight' => 2,
      ];
    }
    else {
      $operations['enable'] = [
        'title' => $this->t('Enable'),
        'url' => Url::fromRoute('entity.tour.enable', ['tour' => $entity->id()]),
        'attributes' => [
          'class' => ['use-ajax'],
          'aria-label' => $this->t('Enable @tour', ['@tour' => $entity->label()]),
          'data-drupal-selector' => 'tour-listing-' . $entity->id(),
        ],
        'weight' => -1,
      ];
    }

    $operations['delete'] = [
      'title' => $this->t('Delete'),
      'url' => $entity->toUrl('delete-form'),
      'attributes' => [
        'aria-label' => $this->t('Delete @tour', ['@tour' => $entity->label()]),
      ],
      'weight' => 40,
    ];

    $operations['clone'] = [
      'title' => $this->t('Clone'),
      'url' => $entity->toUrl('clone-form'),
      'attributes' => [
        'aria-label' => $this->t('Clone @tour', ['@tour' => $entity->label()]),
      ],
      'weight' => 11,
    ];

    $user = $this->currentUser;
    if ($user->hasPermission('export configuration')) {
      $operations['export-config'] = [
        'title' => $this->t('Export'),
        'url' => Url::fromRoute('config.export_single', [
          'config_type' => 'tour',
          'config_name' => $entity->getOriginalId(),
        ]),
        'attributes' => [
          'aria-label' => $this->t('Export configuration for @tour', ['@tour' => $entity->label()]),
        ],
        'weight' => 13,
      ];
    }

    uasort($operations, '\Drupal\Component\Utility\SortArray::sortByWeightElement');
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build['#type'] = 'container';
    $build['#attributes']['id'] = 'tours-list';

    $entities = $this->load();

    $build['enabled']['heading']['#markup'] = '<h2>' . $this->t('Enabled', [], ['context' => 'Plural']) . '</h2>';
    $build['disabled']['heading']['#markup'] = '<h2>' . $this->t('Disabled', [], ['context' => 'Plural']) . '</h2>';
    foreach (['enabled', 'disabled'] as $status) {
      $build[$status]['#type'] = 'container';
      $build[$status]['#attributes'] = ['class' => ['tour-list-section', $status]];
      $build[$status]['table'] = [
        '#theme' => 'tour_listing_table',
        '#headers' => $this->buildHeader(),
        '#attributes' => ['class' => [$status]],
      ];
      foreach ($entities[$status] as $entity) {
        $build[$status]['table']['#rows'][$entity->id()] = $this->buildRow($entity);
      }
    }
    $build['enabled']['table']['#empty'] = $this->t('There are no enabled tours.');
    $build['disabled']['table']['#empty'] = $this->t('There are no disabled tours.');

    return $build;
  }

}
