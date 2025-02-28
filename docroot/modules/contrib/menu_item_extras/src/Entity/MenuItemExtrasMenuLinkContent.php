<?php

namespace Drupal\menu_item_extras\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Url;
use Drupal\menu_link_content\Entity\MenuLinkContent;

/**
 * {@inheritdoc}
 */
class MenuItemExtrasMenuLinkContent extends MenuLinkContent implements MenuItemExtrasMenuLinkContentInterface {

  const MENU_LINK_CONTENT_PREFIX = 'menu_link_content:';

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    if (isset($values['menu_name'])) {
      $values += ['bundle' => $values['menu_name']];
    }
    parent::preCreate($storage, $values);
  }

  /**
   * {@inheritdoc}
   */
  public function getUrlObject() {
    if (!$this->link->first()) {
      return Url::fromRoute($this->route_name);
    }
    return parent::getUrlObject();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTagsToInvalidate() {
    $tags = parent::getCacheTagsToInvalidate();
    $parent_id = $this->getParentId();
    if ($this->isNew() && $parent_id) {
      // Need to invalidate the parent to clear the render cache generated
      // in MenuLinkTreeHandler::getMenuLinkItemContent().
      if (strpos($parent_id, self::MENU_LINK_CONTENT_PREFIX) === 0) {
        $parent_uuid = substr($parent_id, strlen(self::MENU_LINK_CONTENT_PREFIX));
        /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
        $storage = $this->entityTypeManager()
          ->getStorage($this->getEntityTypeId());
        $loaded_entities = $storage->loadByProperties(['uuid' => $parent_uuid]);
        $parent = reset($loaded_entities);

        if ($parent) {
          return Cache::mergeTags($tags, [self::MENU_LINK_CONTENT_PREFIX . $parent->id()]);
        }
      }
      else {
        /** @var \Drupal\Core\Menu\MenuLinkManagerInterface $menu_link_manager */
        $menu_link_manager = \Drupal::service('plugin.manager.menu.link');

        if ($menu_link_manager->hasDefinition($parent_id)) {

          /** @var \Drupal\Core\Menu\MenuLinkInterface $parent */
          $parent = $menu_link_manager->createInstance($parent_id);

          if ($parent) {
            return Cache::mergeTags($tags, $parent->getCacheTags());
          }
        }
      }
    }
    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  public function validate() {
    $plugin_id = $this->getTypedData()->getDataDefinition()->getDataType();
    if (!\Drupal::typedDataManager()->hasDefinition($plugin_id)) {
      \Drupal::typedDataManager()->clearCachedDefinitions();
      \Drupal::typedDataManager()->getDefinitions();
    }
    return parent::validate();
  }

}
