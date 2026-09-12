<?php

namespace Drupal\menu_item_extras\Cache;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CalculatedCacheContextInterface;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkInterface;

/**
 * Defines the LinkItemContentActiveTrailsCacheContext service.
 */
class LinkItemContentActiveTrailsCacheContext implements CalculatedCacheContextInterface {

  /**
   * The active link per menu name, for the lifetime of the request.
   *
   * MenuActiveTrail::getActiveLink() is not part of the cache collector that
   * backs getActiveTrailIds(), so every call runs an uncached menu_tree query.
   * This context is resolved once per menu link when the render cache ID is
   * built, so without this the query count scales with the size of the menu on
   * every request -- including render cache hits.
   *
   * @var array<string, \Drupal\Core\Menu\MenuLinkInterface|null>
   */
  protected array $activeLinks = [];

  /**
   * Constructs a LinkItemContentActiveTrailsCacheContext object.
   *
   * @param \Drupal\Core\Menu\MenuActiveTrailInterface $menuActiveTrailService
   *   The menu active trail service.
   */
  public function __construct(protected MenuActiveTrailInterface $menuActiveTrailService) {
  }

  /**
   * Gets the active link for a menu, resolving it at most once per request.
   *
   * @param string $menu_name
   *   The menu name.
   *
   * @return \Drupal\Core\Menu\MenuLinkInterface|null
   *   The active link, or NULL if no link matches.
   */
  protected function getActiveLink(string $menu_name): ?MenuLinkInterface {
    // array_key_exists(), not isset(): NULL is a valid resolved value.
    if (!array_key_exists($menu_name, $this->activeLinks)) {
      $this->activeLinks[$menu_name] = $this->menuActiveTrailService->getActiveLink($menu_name);
    }

    return $this->activeLinks[$menu_name];
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

    $active_trail_link = $this->getActiveLink($menu_name);
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
