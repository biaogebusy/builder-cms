<?php

namespace Drupal\verf\Plugin\views\filter;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\views\Plugin\views\filter\InOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Views filter for entity reference fields.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("verf")
 */
class EntityReference extends InOperator implements ContainerFactoryPluginInterface
{

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The referenceable entities.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]|null
   *
   * @see static::getReferenceableEntities()
   */
  protected $referenceableEntities;

  /**
   * The target entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $targetEntityStorage;

  /**
   * The target entity type.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $targetEntityType;

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new instance.
   *
   * @param mixed[] $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed[] $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityStorageInterface $target_entity_storage
   *   The target entity storage.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Entity\EntityTypeInterface $target_entity_type
   *   The target entity type.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, LanguageManagerInterface $language_manager, EntityStorageInterface $target_entity_storage, EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityTypeInterface $target_entity_type, ModuleHandlerInterface $module_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->targetEntityStorage = $target_entity_storage;
    $this->targetEntityType = $target_entity_type;
    $this->languageManager = $language_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $container->get('entity_type.manager');
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('language_manager'), $entity_type_manager->getStorage($configuration['verf_target_entity_type_id']), $container->get('entity_type.bundle.info'), $entity_type_manager->getDefinition($configuration['verf_target_entity_type_id']), $container->get('module_handler'));
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['verf_target_bundles'] = [
      'default' => [],
    ];

    $options['show_unpublished'] = [
      'default' => FALSE,
    ];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['show_unpublished'] = [
      '#type' => 'checkbox',
      '#title' => t("Ignore access control"),
      '#default_value' => $this->options['show_unpublished'],
    ];

    if (!$this->targetEntityType->hasKey('bundle')) {
      return $form;
    }

    $options = [];
    $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo($this->targetEntityType->id());
    foreach ($bundleInfo as $id => $info) {
      $options[$id] = $info['label'];
    }

    $form['verf_target_bundles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Target entity bundles to filter by'),
      '#options' => $options,
      '#default_value' => array_filter($this->options['verf_target_bundles']),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    parent::valueForm($form, $form_state);
    // Apply cacheability metadata, because the parent class does not.
    // @todo Remove this once https://www.drupal.org/node/2754103 is fixed.
    $cacheability_metdata = CacheableMetadata::createFromObject($this);
    $cacheability_metdata->applyTo($form);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if ($this->valueOptions !== NULL) {
      return $this->valueOptions;
    }

    $this->valueOptions = [];
    foreach ($this->getReferenceableEntities() as $entity) {
      $current_content_language_id = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
      if ($entity instanceof TranslatableInterface && $entity->hasTranslation($current_content_language_id)) {
        $entity = $entity->getTranslation($current_content_language_id);
      }

      // Use the special view label, since some entities allow the label to be
      // viewed, even if the entity is not allowed to be viewed.
      if (!$entity->access('view label')) {
        $this->valueOptions[$entity->id()] = new TranslatableMarkup('- Restricted access -');
        continue;
      }
      $this->valueOptions[$entity->id()] = $entity->label();
    }
    natcasesort($this->valueOptions);

    return $this->valueOptions;
  }

  /**
   * Gets the entities that can be filtered by.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   */
  protected function getReferenceableEntities() {
    if ($this->referenceableEntities !== NULL) {
      return $this->referenceableEntities;
    }
    $target_ids = NULL;

    // Filter by bundle if if the plugin was configured to do so.
    $target_bundles = array_filter($this->options['verf_target_bundles']);
    if ($this->targetEntityType->hasKey('bundle') && $target_bundles) {
      $query = $this->targetEntityStorage->getQuery();
      $query->accessCheck(TRUE);
      $query->condition($this->targetEntityType->getKey('bundle'), $target_bundles, 'IN');
      $target_ids = $query->execute();
    }

    $this->referenceableEntities = $this->targetEntityStorage->loadMultiple($target_ids);

    if (!$this->options['show_unpublished']) {
      $filtered_target_ids = [];
      foreach ($this->referenceableEntities as $entity) {
        if ($entity->access('view')) {
          $filtered_target_ids[] = $entity;
        }
      }
      $this->referenceableEntities = $filtered_target_ids;
    }
    $referenceable_entities = $this->referenceableEntities;
    $this->moduleHandler->alter('verf_entites_options', $referenceable_entities);
    return $referenceable_entities;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $cache_tags = Cache::mergeTags(parent::getCacheTags(), $this->view->storage->getCacheTags());
    $cache_tags = Cache::mergeTags($cache_tags, $this->targetEntityType->getListCacheTags());

    return $cache_tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $cache_contexts = Cache::mergeContexts(parent::getCacheContexts(), $this->view->storage->getCacheContexts());
    $cache_contexts = Cache::mergeContexts($cache_contexts, $this->targetEntityType->getListCacheContexts());

    return $cache_contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    $cache_max_age = Cache::mergeMaxAges(parent::getCacheMaxAge(), $this->view->storage->getCacheMaxAge());

    return $cache_max_age;
  }

}
