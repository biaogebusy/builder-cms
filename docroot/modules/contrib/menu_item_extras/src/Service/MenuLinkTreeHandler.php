<?php

namespace Drupal\menu_item_extras\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\menu_link_content\MenuLinkContentInterface;

/**
 * Class for service MenuLinkTreeHandler.
 */
class MenuLinkTreeHandler implements MenuLinkTreeHandlerInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Constructs a new MenuLinkTreeHandler.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $entity_repository) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function getMenuLinkItemEntity(MenuLinkInterface $link) {
    $menu_item = NULL;
    $metadata = $link->getMetaData();
    if (!empty($metadata['entity_id'])) {
      /** @var \Drupal\menu_link_content\Entity\MenuLinkContent $menu_item */
      $menu_item = $this->entityTypeManager
        ->getStorage('menu_link_content')
        ->load($metadata['entity_id']);
    }
    else {
      $menu_item = $this->entityTypeManager
        ->getStorage('menu_link_content')
        ->create($link->getPluginDefinition());
    }
    if ($menu_item) {
      $menu_item = $this->entityRepository->getTranslationFromContext($menu_item);
    }
    return $menu_item;
  }

  /**
   * {@inheritdoc}
   */
  public function getMenuLinkContentViewMode(MenuLinkContentInterface $entity) {
    $view_mode = 'default';
    if (!$entity->get('view_mode')->isEmpty()) {
      $value = $entity->get('view_mode')->first()->getValue();
      if (!empty($value['value'])) {
        $view_mode = $value['value'];
      }
    }
    return $view_mode;
  }

  /**
   * {@inheritdoc}
   */
  public function getMenuLinkItemContent(MenuLinkContentInterface $entity, $menu_level = NULL, $show_item_link = FALSE) {
    // Build the render array for this menu link.
    $view_builder = $this->entityTypeManager->getViewBuilder('menu_link_content');
    $view_mode = $entity->id() ? $this->getMenuLinkContentViewMode($entity) : 'default';
    $render_output = $view_builder->view($entity, $view_mode);

    // Build the entity view ourselves and unset the #pre_render so that it
    // doesn't run twice later on, when rendered.
    // This gives us access to all fields immediately in the menu template.
    $render_output = $view_builder->build($render_output);
    array_pop($render_output['#pre_render']);

    // Unset cache, handled by menu_item_extras_link_item_content_active_trails.
    unset($render_output['#cache']);

    // Add other properties.
    $render_output['#show_item_link'] = $show_item_link;
    if (!is_null($menu_level)) {
      $render_output['#menu_level'] = $menu_level;
    }

    return $render_output;
  }

  /**
   * {@inheritdoc}
   */
  public function getMenuLinkItemViewMode(MenuLinkInterface $link) {
    $entity = $this->getMenuLinkItemEntity($link);
    if ($entity) {
      return $this->getMenuLinkContentViewMode($entity);
    }

    return 'default';
  }

  /**
   * {@inheritdoc}
   */
  public function isMenuLinkDisplayedChildren(MenuLinkInterface $link) {
    /** @var \Drupal\menu_link_content\Entity\MenuLinkContent $menu_item */
    $entity = $this->getMenuLinkItemEntity($link);
    if ($entity) {
      $view_mode = $this->getMenuLinkContentViewMode($entity);
      /** @var \Drupal\Core\Entity\Entity\EntityViewDisplay $display */
      $display = $this->entityTypeManager
        ->getStorage('entity_view_display')
        ->load($entity->getEntityTypeId() . '.' . $entity->bundle() . '.' . $view_mode);
      if ($display->getComponent('children')) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function processMenuLinkTree(array &$items, $menu_name, $menu_level = -1, $show_item_link = FALSE) {
    $menu_level++;
    foreach ($items as &$item) {
      $content = [];
      if (isset($item['original_link'])) {
        $content['#item'] = $item;
        $content['entity'] = $this->getMenuLinkItemEntity($item['original_link']);
        $content['content'] = $content['entity'] ? $this->getMenuLinkItemContent($content['entity'], $menu_level, $show_item_link) : NULL;
        $content['content']['#cache']['contexts'][] = 'menu_item_extras_link_item_content_active_trails:' . $menu_name . ':' . $item['original_link']->getDerivativeId();
        $content['menu_level'] = $menu_level;
      }
      // Process subitems.
      if (!empty($item['below'])) {
        $content['content']['children']['#items'] = $this->processMenuLinkTree($item['below'], $menu_name, $menu_level, $show_item_link);
        $content['content']['children']['#theme'] = 'menu_levels';
        $content['content']['children']['#menu_name'] = $menu_name;
        $content['content']['children']['#menu_level'] = $menu_level + 1;
      }
      $item = array_merge($item, $content);
    }
    return $items;
  }

}
