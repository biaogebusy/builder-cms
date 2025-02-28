<?php

namespace Drupal\views_add_button;

use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Class ViewsAddButtonUtilities
 * @package Drupal\views_add_button
 */
class ViewsAddButtonUtilities {

  /**
   * Build Bundle Type List.
   */
  public static function createPluginList() {
    $plugin_manager = \Drupal::service('plugin.manager.views_add_button');
    $plugin_definitions = $plugin_manager->getDefinitions();

    $options = ['Any Entity' => []];
    $entity_info = \Drupal::entityTypeManager()->getDefinitions();
    foreach ($plugin_definitions as $pd) {
      $label = $pd['label'];
      if ($pd['label'] instanceof TranslatableMarkup) {
        $label = $pd['label']->render();
      }

      $type_info = isset($pd['target_entity']) && isset($entity_info[$pd['target_entity']]) ? $entity_info[$pd['target_entity']] : 'default';
      $type_label = t('Any Entity');
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
   */
  public static function createEntityBundleList() {
    $ret = [];
    $entity_info = \Drupal::entityTypeManager()->getDefinitions();
    $bundle_info = \Drupal::service('entity_type.bundle.info');
    foreach ($entity_info as $type => $info) {
      // Is this a content/front-facing entity?
      if ($info instanceof ContentEntityType) {
        $label = $info->getLabel();
        if ($label instanceof TranslatableMarkup) {
          $label = $label->render();
        }
        $ret[$label] = [];
        $bundles = $bundle_info->getBundleInfo($type);
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

}