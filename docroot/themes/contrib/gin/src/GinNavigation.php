<?php

namespace Drupal\gin;

use Drupal\block_content\Entity\BlockContentType;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Vocabulary;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Service to handle overridden user settings.
 */
class GinNavigation implements ContainerInjectionInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static();
  }

  /**
   * Get Navigation Admin Menu Items.
   */
  public function getNavigationAdminMenuItems(): array {
    $parameters = new MenuTreeParameters();
    $parameters->setMinDepth(2)->setMaxDepth(4)->onlyEnabledLinks();
    /** @var Drupal\Core\Menu\MenuLinkTree $menu_tree */
    $menu_tree = \Drupal::service('menu.link_tree');
    $tree = $menu_tree->load('admin', $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
      ['callable' => 'toolbar_menu_navigation_links'],
    ];
    $tree = $menu_tree->transform($tree, $manipulators);
    $build = $menu_tree->build($tree);
    /** @var \Drupal\Core\Menu\MenuLinkInterface $link */
    $first_link = reset($tree)->link;
    // Get the menu name of the first link.
    $menu_name = $first_link->getMenuName();
    $build['#menu_name'] = $menu_name;
    $build['#theme'] = 'menu_region__middle';

    // Loop through menu items and add the plugin id as a class.
    foreach ($tree as $item) {
      $plugin_id = $item->link->getPluginId();
      $plugin_class = str_replace('.', '_', $plugin_id);
      $build['#items'][$plugin_id]['class'] = $plugin_class;
    }

    // Remove content and help from admin menu.
    unset($build['#items']['system.admin_content']);
    unset($build['#items']['help.main']);
    $build['#title'] = t('Administration');

    return $build;
  }

  /**
   * Get Navigation Bookmarks.
   */
  public function getNavigationBookmarksMenuItems(): array {
    // Check if the shortcut module is installed.
    if (\Drupal::hasService('shortcut.lazy_builders') === TRUE) {
      $shortcuts = \Drupal::service('shortcut.lazy_builders')->lazyLinks()['shortcuts'];
      $shortcuts['#theme'] = 'menu_region__top';
      $shortcuts['#menu_name'] = 'bookmarks';
      $shortcuts['#title'] = t('Bookmarks');
      return $shortcuts;
    }
    else {
      return [];
    }
  }

  /**
   * Get Navigation Create menu.
   */
  public function getNavigationCreateMenuItems(): array {
    // Get the Entity Type Manager service.
    $entity_type_manager = \Drupal::entityTypeManager();

    // Needs to be this syntax to
    // support older PHP versions
    // for Druapl 9.0+.
    $create_type_items = [];
    $create_item_url = '';

    // Get node types.
    if ($entity_type_manager->hasDefinition('node')) {
      $create_item_url = Url::fromRoute('node.add_page')->toString();
      $content_types = $entity_type_manager->getStorage('node_type')->loadMultiple();
      $content_type_items = [];

      foreach ($content_types as $item) {
        $content_type_items[] = [
          'title' => $item->label(),
          'class' => $item->id(),
          'url' => $create_item_url,
        ];
      }

      $create_type_items = array_merge($content_type_items);
    }

    // Get block types.
    if ($entity_type_manager->hasDefinition('block_content')) {
      $block_content_types = BlockContentType::loadMultiple();
      $block_type_items = [];

      foreach ($block_content_types as $item) {
        $block_type_items[] = [
          'title' => $item->label(),
          'class' => $item->id(),
          'url' => Url::fromRoute('block_content.add_form', ['block_content_type' => $item->id()]),
        ];
      }

      $create_type_items = array_merge(
        $create_type_items,
        [
          [
            'title' => t('Blocks'),
            'class' => 'blocks',
            'url' => '',
            'below' => $block_type_items,
          ],
        ]
      );
    }

    // Get media types.
    if ($entity_type_manager->hasDefinition('media')) {
      $media_types = $entity_type_manager->getStorage('media_type')->loadMultiple();
      $media_type_items = [];

      foreach ($media_types as $item) {
        $media_type_items[] = [
          'title' => $item->label(),
          'class' => $item->label(),
          'url' => Url::fromRoute('entity.media.add_form', ['media_type' => $item->id()]),
        ];
      }

      $create_type_items = array_merge(
        $create_type_items,
        [
          [
            'title' => t('Media'),
            'class' => 'media',
            'url' => '',
            'below' => $media_type_items,
          ],
        ]
      );
    }

    // Get taxomony types.
    if ($entity_type_manager->hasDefinition('taxonomy_term')) {
      $taxonomy_types = Vocabulary::loadMultiple();
      $taxonomy_type_items = [];

      foreach ($taxonomy_types as $item) {
        $taxonomy_type_items[] = [
          'title' => $item->label(),
          'class' => $item->id(),
          'url' => Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $item->id()]),
        ];
      }

      $create_type_items = array_merge(
        $create_type_items,
        [
          [
            'title' => t('Taxonomy'),
            'class' => 'taxonomy',
            'url' => '',
            'below' => $taxonomy_type_items,
          ],
        ]
      );
    }

    if (!$create_type_items && !$create_item_url) {
      return [];
    }

    // Generate menu items.
    $create_items['create'] = [
      'title' => t('Create'),
      'class' => 'create',
      'url' => $create_item_url,
      'below' => $create_type_items,
    ];

    return [
      '#theme' => 'menu_region__middle',
      '#items' => $create_items,
      '#menu_name' => 'create',
      '#title' => t('Create Navigation'),
    ];
  }

  /**
   * Get Navigation Content menu.
   */
  public function getNavigationContentMenuItems(): array {
    $entity_type_manager = \Drupal::entityTypeManager();

    $create_content_items = [];

    // Get Content menu item.
    if ($entity_type_manager->hasDefinition('node')) {
      $create_content_items['content'] = [
        'title' => t('Content'),
        'class' => 'content',
        'url' => Url::fromRoute('system.admin_content')->toString(),
      ];
    }

    // Get Blocks menu item.
    if ($entity_type_manager->hasDefinition('block_content')) {
      $create_content_items['blocks'] = [
        'title' => t('Blocks'),
        'class' => 'blocks',
        'url' => Url::fromRoute('entity.block_content.collection')->toString(),
      ];
    }

    // Get File menu item.
    if ($entity_type_manager->hasDefinition('file')) {
      $create_content_items['files'] = [
        'title' => t('Files'),
        'class' => 'files',
        'url' => '/admin/content/files',
      ];
    }

    // Get Media menu item.
    if ($entity_type_manager->hasDefinition('media')) {
      $create_content_items['media'] = [
        'title' => t('Media'),
        'class' => 'media',
        'url' => '/admin/content/media',
      ];
    }

    return [
      '#theme' => 'menu_region__middle',
      '#items' => $create_content_items,
      '#menu_name' => 'content',
      '#title' => t('Content Navigation'),
    ];
  }

  /**
   * Get Navigation User menu.
   */
  public function getMenuNavigationUserItems(): array {
    $user_items = [
      [
        'title' => t('Profile'),
        'class' => 'profile',
        'url' => Url::fromRoute('user.page')->toString(),
      ],
      [
        'title' => t('Settings'),
        'class' => 'settings',
        'url' => Url::fromRoute('entity.user.admin_form')->toString(),
      ],
      [
        'title' => t('Log out'),
        'class' => 'logout',
        'url' => Url::fromRoute('user.logout')->toString(),
      ],
    ];
    return [
      '#theme' => 'menu_region__bottom',
      '#items' => $user_items,
      '#menu_name' => 'user',
      '#title' => t('User'),
    ];
  }

  /**
   * Get Navigation.
   */
  public function getNavigationStructure() {
    // Get navigation items.
    $menu['top']['create'] = $this->getNavigationCreateMenuItems();
    $menu['middle']['content'] = $this->getNavigationContentMenuItems();
    $menu['middle']['admin'] = $this->getNavigationAdminMenuItems();
    $menu['bottom']['user'] = $this->getMenuNavigationUserItems();

    return [
      '#theme' => 'navigation',
      '#menu_top' => $menu['top'],
      '#menu_middle' => $menu['middle'],
      '#menu_bottom' => $menu['bottom'],
      '#attached' => [
        'library' => [
          'gin/navigation',
        ],
      ],
      '#access' => \Drupal::currentUser()->hasPermission('access toolbar'),
    ];
  }

  /**
   * Get Active trail.
   */
  public function getNavigationActiveTrail() {
    // Get the breadcrumb paths to maintain active trail in the toolbar.
    $links = \Drupal::service('breadcrumb')->build(\Drupal::routeMatch())->getLinks();
    $paths = [];
    foreach ($links as $link) {
      $paths[] = $link->getUrl()->getInternalPath();
    }

    return $paths;
  }

}
