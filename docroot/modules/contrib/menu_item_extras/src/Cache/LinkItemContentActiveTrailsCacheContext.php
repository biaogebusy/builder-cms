<?php

namespace Drupal\menu_item_extras\Cache;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CalculatedCacheContextInterface;
use Drupal\Core\Menu\MenuActiveTrailInterface;

/**
 * Defines the LinkItemContentActiveTrailsCacheContext service.
 */
class LinkItemContentActiveTrailsCacheContext implements CalculatedCacheContextInterface {

  /**
   * Constructs a LinkItemContentActiveTrailsCacheContext object.
   *
   * @param \Drupal\Core\Menu\MenuActiveTrailInterface $menuActiveTrailService
   *   The menu active trail service.
   */
  public function __construct(protected MenuActiveTrailInterface $menuActiveTrailService) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t("Active link item content");
  }

  /**
   * {@inheritdoc}
   */
  public function getContext($parameter = NULL) {
    [$menu_name, $menu_link_id] = explode(':', $parameter);

    if (!$menu_name) {
      throw new \LogicException('No menu name provided for menu.active_trails cache context.');
    }

    $active_trail_link = $this->menuActiveTrailService->getActiveLink($menu_name);
    $active_trail_ids = array_values($this->menuActiveTrailService->getActiveTrailIds($menu_name));

    if ($active_trail_link && $active_trail_link->getDerivativeId() == $menu_link_id) {
      return 'link_item_content.active.' . $menu_link_id;
    }
    elseif (in_array('menu_link_content:' . $menu_link_id, $active_trail_ids)) {
      return 'link_item_content.active_trail';
    }
    else {
      return 'link_item_content.inactive';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($parameter = NULL) {
    [$menu_name] = explode(':', $parameter);

    if (!$menu_name) {
      throw new \LogicException('No menu name provided for menu.active_trails cache context.');
    }

    $cacheable_metadata = new CacheableMetadata();
    return $cacheable_metadata->setCacheTags(["config:system.menu.$menu_name"]);
  }

}
