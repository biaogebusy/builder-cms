<?php

namespace Drupal\ai\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\system\SystemManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the AI administration overview page.
 *
 * Provides a grouped display similar to /admin/config, with items that don't
 * belong to a category grouped into a "Miscellaneous" section.
 */
class AiAdminController extends ControllerBase {

  use StringTranslationTrait;

  /**
   * The menu link tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected MenuLinkTreeInterface $menuLinkTree;

  /**
   * The system manager service.
   *
   * @var \Drupal\system\SystemManager
   */
  protected SystemManager $systemManager;

  /**
   * Constructs an AiAdminController object.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_link_tree
   *   The menu link tree service.
   * @param \Drupal\system\SystemManager $system_manager
   *   The system manager service.
   */
  public function __construct(
    MenuLinkTreeInterface $menu_link_tree,
    SystemManager $system_manager,
  ) {
    $this->menuLinkTree = $menu_link_tree;
    $this->systemManager = $system_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('menu.link_tree'),
      $container->get('system.manager'),
    );
  }

  /**
   * Provides the AI administration overview page.
   *
   * Items are grouped by their parent category. Items without a category
   * (direct children of ai.admin_settings that don't have their own children)
   * are grouped into a "Miscellaneous" section.
   *
   * @return array
   *   A renderable array of the administration overview page.
   */
  public function overview(): array {
    $link_id = 'ai.admin_settings';
    $parameters = new MenuTreeParameters();
    $parameters->setRoot($link_id)->excludeRoot()->setTopLevelOnly()->onlyEnabledLinks();
    $tree = $this->menuLinkTree->load(NULL, $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuLinkTree->transform($tree, $manipulators);
    $tree_access_cacheability = new CacheableMetadata();

    // Use indexed array to preserve weight-based order from tree manipulators.
    $blocks = [];
    $miscellaneous_items = [];

    foreach ($tree as $element) {
      $tree_access_cacheability = $tree_access_cacheability->merge(
        CacheableMetadata::createFromObject($element->access)
      );

      if (!$element->access->isAllowed()) {
        continue;
      }

      $link = $element->link;
      $children = $this->systemManager->getAdminBlock($link);

      if (!empty($children)) {
        // This is a category with children - render as its own block.
        $blocks[] = [
          'title' => $link->getTitle(),
          'description' => $link->getDescription(),
          'content' => [
            '#theme' => 'admin_block_content',
            '#content' => $children,
          ],
        ];
      }
      else {
        // This item has no children - add to miscellaneous.
        $miscellaneous_items[] = [
          'title' => $link->getTitle(),
          'options' => $link->getOptions(),
          'description' => $link->getDescription(),
          'url' => $link->getUrlObject(),
        ];
      }
    }

    // Add miscellaneous items as a block at the end if there are any.
    if (!empty($miscellaneous_items)) {
      $blocks[] = [
        'title' => $this->t('Miscellaneous'),
        'description' => $this->t('Other AI configuration options.'),
        'content' => [
          '#theme' => 'admin_block_content',
          '#content' => $miscellaneous_items,
        ],
      ];
    }

    if ($blocks) {
      $build = [
        '#theme' => 'admin_page',
        '#blocks' => $blocks,
      ];
      $tree_access_cacheability->applyTo($build);
      return $build;
    }

    $build = [
      '#markup' => $this->t('You do not have any administrative items.'),
    ];
    $tree_access_cacheability->applyTo($build);
    return $build;
  }

}
