<?php

namespace Drupal\views_add_button\Service;

use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views_add_button\ViewsAddButtonManager;

/**
 * The ViewsAddButton service.
 *
 * @package Drupal\views_add_button\Service
 */
class ViewsAddButtonService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected EntityTypeBundleInfoInterface $entityTypeBundleInfo;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The plugin manager.
   *
   * @var \Drupal\views_add_button\ViewsAddButtonManager
   */
  protected ViewsAddButtonManager $viewsAddButtonManager;

  /**
   * ViewsAddButtonService constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfo $bundle_info
   *   The entity type bundle info service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory.
   * @param \Drupal\views_add_button\ViewsAddButtonManager $plugin_manager
   *   The views add button plugin manager.
   */
  public function __construct(EntityTypeManager $manager, EntityTypeBundleInfo $bundle_info, ConfigFactoryInterface $config, ViewsAddButtonManager $plugin_manager) {
    $this->entityTypeManager = $manager;
    $this->entityTypeBundleInfo = $bundle_info;
    $this->config = $config;
    $this->viewsAddButtonManager = $plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('config.factory'),
      $container->get('plugin.manager.views_add_button')
    );
  }

  /**
   * Build Plugin List.
   *
   * @return array
   *   Array of entity types and their available plugins.
   */
  public function createPluginList() {
    $plugin_definitions = $this->viewsAddButtonManager->getDefinitions();

    $options = [t('Any Entity')->render() => []];
    $entity_info = $this->entityTypeManager->getDefinitions();
    foreach ($plugin_definitions as $pd) {
      $label = $pd['label'];
      if ($pd['label'] instanceof TranslatableMarkup) {
        $label = $pd['label']->render();
      }

      $type_info = isset($pd['target_entity'], $entity_info[$pd['target_entity']]) ? $entity_info[$pd['target_entity']] : 'default';
      $type_label = t('Any Entity')->render();
      if ($type_info instanceof ContentEntityType) {
        $type_label = $type_info->getLabel();
      }
      if ($type_label instanceof TranslatableMarkup) {
        $type_label = $type_label->render();
      }
      $options[$type_label][$pd['id']] = $label;
    }
    return $options;
  }

  /**
   * Build Bundle Type List.
   *
   * @return array
   *   Array of entity bundles, sorted by entity type
   */
  public function createEntityBundleList() {
    $ret = [];
    $entity_info = $this->entityTypeManager->getDefinitions();
    foreach ($entity_info as $type => $info) {
      // Is this a content/front-facing entity?
      if ($info instanceof ContentEntityType) {
        $label = $info->getLabel();
        if ($label instanceof TranslatableMarkup) {
          $label = $label->render();
        }
        $ret[$label] = [];
        $bundles = $this->entityTypeBundleInfo->getBundleInfo($type);
        foreach ($bundles as $key => $bundle) {
          if ($bundle['label'] instanceof TranslatableMarkup) {
            $ret[$label][$type . '+' . $key] = $bundle['label']->render();
          }
          else {
            $ret[$label][$type . '+' . $key] = $bundle['label'];
          }
        }
      }
    }
    return $ret;
  }

  /**
   * Get Plugin definitions.
   *
   * @return array
   *   Plugin definitions.
   */
  public function getPluginDefinitions() {
    return $this->viewsAddButtonManager->getDefinitions();
  }

}
