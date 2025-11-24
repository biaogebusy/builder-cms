<?php

namespace Drupal\views_add_button\Service;

use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views_add_button\ViewsAddButtonManager;

/**
 * Class ViewsAddButtonService
 * @package Drupal\views_add_button\Service
 */
class ViewsAddButtonService {

  /**
   * @var EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * @var EntityTypeBundleInfo
   */
  protected $bundle_info;

  /**
   * @var ConfigFactoryInterface
   */
  protected $config;

  /**
   * @var ViewsAddButtonManager
   */
  protected $plugin_manager;

  /**
   * ViewsAddButtonService constructor.
   * @param EntityTypeManager $manager
   * @param EntityTypeBundleInfo $bundle_info
   * @param ConfigFactoryInterface $config
   * @param ViewsAddButtonManager $plugin_manager
   */
  public function __construct(EntityTypeManager $manager, EntityTypeBundleInfo $bundle_info, ConfigFactoryInterface $config, ViewsAddButtonManager $plugin_manager) {
    $this->entityTypeManager = $manager;
    $this->bundle_info = $bundle_info;
    $this->config = $config;
    $this->plugin_manager = $plugin_manager;
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
    $plugin_definitions = $this->plugin_manager->getDefinitions();

    $options = [t('Any Entity')->render() => []];
    $entity_info = $this->entityTypeManager->getDefinitions();
    foreach ($plugin_definitions as $pd) {
      $label = $pd['label'];
      if ($pd['label'] instanceof TranslatableMarkup) {
        $label = $pd['label']->render();
      }

      $type_info = isset($pd['target_entity']) && isset($entity_info[$pd['target_entity']]) ? $entity_info[$pd['target_entity']] : 'default';
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
        $bundles = $this->bundle_info->getBundleInfo($type);
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
   * @return array
   */
  public function getPluginDefinitions() {
    return $this->plugin_manager->getDefinitions();
  }

}
