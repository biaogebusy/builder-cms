<?php

namespace Drupal\eck\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Url;
use Drupal\eck\EckEntityTypeBundleInfo;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides a listing of ECK entities.
 *
 * @ingroup eck
 */
class EckEntityTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;


  /**
   * EckEntityTypeBundleInfo service.
   *
   * @var \Drupal\eck\EckEntityTypeBundleInfo
   */
  protected $eckBundleInfo;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager'),
      $container->get('eck.entity_type.bundle.info')
    );
  }

  /**
   * Constructs a new EntityListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\eck\EckEntityTypeBundleInfo $bundle_info
   *   ECK Entity Bundle Info service.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityTypeManagerInterface $entity_type_manager, EckEntityTypeBundleInfo $bundle_info) {
    $storage = $entity_type_manager->getStorage($entity_type->id());
    parent::__construct($entity_type, $storage);
    $this->entityTypeManager = $entity_type_manager;
    $this->eckBundleInfo = $bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Entity Type');
    $header['machine_name'] = $this->t('Machine Name');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    $row['machine_name'] = $entity->id();

    if (!$this->eckBundleInfo->entityTypeHasBundles($entity->id())) {
      $row['operations']['data']['#links']['add_bundle'] = [
        'title' => $this->t('Add bundle'),
        'url' => new Url('eck.entity.' . $entity->id() . '_type.add'),
      ];
    }
    else {
      // Add link to list operation.
      $row['operations']['data']['#links']['add_content'] = [
        'title' => $this->t('Add content'),
        'url' => new Url('eck.entity.add_page', ['eck_entity_type' => $entity->id()]),
      ];
      // Directly link to the add entity page if there is only one bundle.
      if ($this->eckBundleInfo->entityTypeBundleCount($entity->id()) === 1) {
        $bundle_machine_names = $this->eckBundleInfo->getEntityTypeBundleMachineNames($entity->id());
        $arguments = [
          'eck_entity_type' => $entity->id(),
          'eck_entity_bundle' => reset($bundle_machine_names),
        ];
        $row['operations']['data']['#links']['add_content']['url'] = new Url('eck.entity.add', $arguments);
      }

      $contentExists = (bool) $this->entityTypeManager->getStorage($entity->id())
        ->getQuery()
        ->range(0, 1)
        ->execute();
      if ($contentExists) {
        // Add link to list operation.
        $row['operations']['data']['#links']['content_list'] = [
          'title' => $this->t('Content list'),
          'url' => new Url('eck.entity.' . $entity->id() . '.list'),
        ];
      }
    }

    $row['operations']['data']['#links']['bundle_list'] = [
      'title' => $this->t('Bundle list'),
      'url' => new Url('eck.entity.' . $entity->id() . '_type.list'),
    ];

    return array_merge_recursive($row, parent::buildRow($entity));
  }

}
