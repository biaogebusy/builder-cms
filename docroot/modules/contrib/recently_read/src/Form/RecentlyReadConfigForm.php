<?php

namespace Drupal\recently_read\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\recently_read\Entity\RecentlyReadType;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Cache\CacheBackendInterface;

/**
 * Class RecentlyReadConfig.
 *
 * @package Drupal\recently_read\Form
 */
class RecentlyReadConfigForm extends ConfigFormBase {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Service entity_type.manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Service cache.default.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Constructs RecentlyReadTypeList object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Service entity_type.manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackendInterface
   *   Service cache.default.
   */
  public function __construct(ConfigFactoryInterface $configFactory, EntityTypeManagerInterface $entityTypeManager, CacheBackendInterface $cacheBackendInterface) {
    parent::__construct($configFactory);
    $this->entityTypeManager = $entityTypeManager;
    $this->cache = $cacheBackendInterface;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('cache.default')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'delete_config',
      'delete_time',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'recently_read_config';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('recently_read.configuration');

    $recentlyReadEntityTypes = [];
    // Get all content entity types.
    $entityTypes = $this->entityTypeManager->getDefinitions();
    foreach ($entityTypes as $entityType) {
      if ($entityType->getGroup() === 'content') {
        $recentlyReadEntityTypes[$entityType->id()] = $entityType->getLabel();
      }
    }

    $enabledEntityTypes = array_keys(RecentlyReadType::loadMultiple());

    $form['entity_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Entity types'),
      '#options' => $recentlyReadEntityTypes,
      '#default_value' => $enabledEntityTypes,
    ];
    $form['delete_config'] = [
      '#type' => 'radios',
      '#title' => 'Records delete options',
      '#options' => [
        'time' => $this->t('Time based'),
        'count' => $this->t('Count based'),
        'never' => $this->t('Never'),
      ],
      '#default_value' => $config->get('delete_config'),
    ];

    $delete_time_options = [
      '-1 hours' => '1 hours',
      '-1 day' => '1 day',
      '-1 week' => '1 Week',
      '-1 month' => '1 Month',
      '-1 year' => '1 Year',
    ];
    $form['delete_time'] = [
      '#type' => 'select',
      '#title' => $this->t('Delete records older then'),
      '#description' => $this->t('When cron is executed, delete records that are older then selected value.'),
      '#options' => $delete_time_options,
      '#default_value' => $config->get('delete_time'),
      '#states' => [
        'visible' => [
          ':input[name="delete_config"]' => ['value' => 'time'],
        ],
      ],
    ];
    $form['count'] = [
      '#type' => 'number',
      '#title' => $this->t('Max records'),
      '#description' => $this->t('Allowed number of records per user or session (if user is anonymous). Older records will be removed.'),
      '#default_value' => $config->get('count'),
      '#states' => [
        'visible' => [
          ':input[name="delete_config"]' => ['value' => 'count'],
        ],
        'required' => [
          ':input[name="delete_config"]' => ['value' => 'count'],
        ],
      ],
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $selectedEntityTypes = $form_state->getValue('entity_types');
    // Remove non-selected items.
    $selectedEntityTypes = array_filter($selectedEntityTypes);

    $enabledEntityTypes = array_keys(RecentlyReadType::loadMultiple());
    $toAdd = array_diff($selectedEntityTypes, $enabledEntityTypes);
    $toRemove = array_diff($enabledEntityTypes, $selectedEntityTypes);

    foreach ($toAdd as $entityType) {
      RecentlyReadType::create([
        'id' => $entityType,
        'label' => $this->entityTypeManager->getDefinition($entityType)->getLabel(),
      ])->save();
    }

    foreach ($toRemove as $entityType) {
      $loadedRecentlyReadType = RecentlyReadType::load($entityType);
      if ($loadedRecentlyReadType) {
        $loadedRecentlyReadType->delete();
      }
    }

    $config = $this->configFactory->getEditable('recently_read.configuration');
    $config->set('delete_config', $form_state->getValue('delete_config'));
    $config->set('delete_time', $form_state->getValue('delete_time'));
    $config->set('count', $form_state->getValue('count'));
    $config->save();

    // Clear caches to register the new relationship.
    $this->cache->invalidateAll();
  }

}
