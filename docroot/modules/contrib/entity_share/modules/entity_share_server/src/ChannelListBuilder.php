<?php

declare(strict_types = 1);

namespace Drupal\entity_share_server;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of Channel entities.
 */
class ChannelListBuilder extends ConfigEntityListBuilder {

  /**
   * The bundle infos from the website.
   *
   * @var array
   */
  protected $bundleInfos;

  /**
   * The entity type labels.
   *
   * @var array
   */
  protected $entityTypeLabels;

  /**
   * The site languages.
   *
   * @var \Drupal\Core\Language\LanguageInterface[]
   */
  protected $languages;

  /**
   * Constructs a new ActionListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The action storage.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   * @param \Drupal\Core\Entity\EntityTypeRepositoryInterface $entity_type_repository
   *   The entity type repository service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    EntityTypeRepositoryInterface $entity_type_repository,
    LanguageManagerInterface $language_manager
  ) {
    parent::__construct($entity_type, $storage);
    $this->bundleInfos = $entity_type_bundle_info->getAllBundleInfo();
    $this->entityTypeLabels = $entity_type_repository->getEntityTypeLabels();
    $this->languages = $language_manager->getLanguages(LanguageInterface::STATE_ALL);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.repository'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [];
    $header['label'] = $this->t('Channel');
    $header['entity_type'] = $this->t('Entity type');
    $header['bundle'] = $this->t('Bundle');
    $header['language'] = $this->t('Language');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $entity_type = $entity->get('channel_entity_type');
    $bundle = $entity->get('channel_bundle');

    $row = [];
    $row['label'] = $entity->label() . ' (' . $entity->id() . ')';
    $row['entity_type'] = $this->entityTypeLabels[$entity_type];
    $row['bundle'] = $this->bundleInfos[$entity_type][$bundle]['label'];
    $row['language'] = $this->languages[$entity->get('channel_langcode') ?? LanguageInterface::LANGCODE_NOT_SPECIFIED]->getName();
    return $row + parent::buildRow($entity);
  }

}
