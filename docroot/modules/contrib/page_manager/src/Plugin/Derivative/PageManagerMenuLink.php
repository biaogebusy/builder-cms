<?php

namespace Drupal\page_manager\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides menu links for Page manager.
 *
 * @see \Drupal\page_manager\Plugin\Menu\PageManagerMenuLink
 */
class PageManagerMenuLink extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a PageManagerMenuLink.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [];

    $page_ids = $this->getApplicablePages();
    foreach ($page_ids as $page_id) {
      /** @var \Drupal\page_manager\PageInterface $page */
      $page = $this->entityTypeManager->getStorage('page')->load($page_id);
      if (!$page || !$page->status()) {
        continue;
      }

      $path = $page->getPath();
      $menu_link_id = 'page_manager.' . $page_id;

      $menu_settings = $page->getMenuSettings();

      // A default local task can additionally place its parent tab in a menu,
      // in which case the link is described by the parent_* settings.
      if ($page->getMenuType() === 'default_menu_tab') {
        if (($menu_settings['parent_menu_type'] ?? 'none') !== 'menu') {
          continue;
        }
        $menu_settings['title'] = $menu_settings['parent_title'] ?? '';
        $menu_settings['menu_name'] = $menu_settings['parent_menu'] ?? 'main';
        $menu_settings['weight'] = $menu_settings['parent_weight'] ?? 0;
      }

      try {
        $route_name = Url::fromUri('internal:' . $path)->getRouteName();
      }
      catch (\UnexpectedValueException $e) {
        continue;
      }

      $this->derivatives[$menu_link_id] = [
        'route_name' => $route_name,
        'id' => $menu_link_id,
        'menu_name' => $menu_settings['menu_name'] ?? 'main',
        'title' => $menu_settings['title'] ?? '',
        'parent' => '',
        'weight' => (int) ($menu_settings['weight'] ?? 0),
        'metadata' => [
          'page_id' => $page_id,
        ],
      ] + $base_plugin_definition;
    }

    return $this->derivatives;
  }

  /**
   * Gets the page ids of pages that provide a menu link.
   *
   * Menu tabs and local actions are not menu links, so only pages placed
   * directly in a menu, and default tabs whose parent is placed in a menu,
   * are considered.
   *
   * @return string[]
   *   The page IDs.
   */
  public function getApplicablePages() {
    return $this->entityTypeManager->getStorage('page')->getQuery()
      ->accessCheck(FALSE)
      ->condition('menu_type', ['default_menu_tab', 'normal_menu'], 'IN')
      ->execute();
  }

}
