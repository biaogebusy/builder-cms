<?php

namespace Drupal\layout_library\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\field_ui\FieldUI;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a list builder for layouts.
 */
class LayoutListBuilder extends EntityListBuilder {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a new LayoutListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   Entity type.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   Entity storage.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundleInfo
   *   Bundle info.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   Current user.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfoInterface $bundleInfo, AccountInterface $currentUser) {
    parent::__construct($entity_type, $storage);
    $this->entityTypeManager = $entityTypeManager;
    $this->bundleInfo = $bundleInfo;
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $entityTypeManager = $container->get('entity_type.manager');
    return new static(
      $entity_type,
      $entityTypeManager->getStorage($entity_type->id()),
      $entityTypeManager,
      $container->get('entity_type.bundle.info'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $rows = [
      'label' => $this->t('Label'),
      'entity_type_id' => $this->t('Entity Type'),
      'bundle' => $this->t('Bundle'),
    ];
    return $rows + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\layout_library\Entity\Layout $entity */
    $targetEntityTypeId = $entity->getTargetEntityType();
    $bundle_info = $this->bundleInfo->getBundleInfo($targetEntityTypeId);
    $bundle_name = isset($bundle_info[$entity->getTargetBundle()]) ? $bundle_info[$entity->getTargetBundle()]['label'] : $entity->getTargetBundle();
    $row = [
      'label' => ['data' => $entity->label()],
      'entity_type_id' => ['data' => $this->entityTypeManager->getDefinition($targetEntityTypeId)->getLabel()],
      'bundle' => ['data' => $bundle_name],
    ];
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    /** @var \Drupal\layout_library\Entity\Layout $entity */
    $operations = [];
    if ($this->currentUser->hasPermission('administer ' . $entity->getTargetEntityType() . ' display')) {
      $operations['edit'] = [
        'title' => $this->t('Edit layout'),
        'weight' => 0,
        'url' => $this->getLayoutBuilderUrl($entity),
      ];
    }
    return $operations + parent::getDefaultOperations($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getLayoutBuilderUrl(Layout $layout) {
    return Url::fromRoute("layout_builder.layout_library.{$layout->getTargetEntityType()}.view", $this->getRouteParameters($layout));
  }

  /**
   * {@inheritdoc}
   */
  protected function getRouteParameters(Layout $layout) {
    $route_parameters = FieldUI::getRouteBundleParameter($this->entityTypeManager->getDefinition($layout->getTargetEntityType()), $layout->getTargetBundle());
    $route_parameters['layout'] = $layout->id();
    return $route_parameters;
  }

}
