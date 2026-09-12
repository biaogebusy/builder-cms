<?php

namespace Drupal\menu_item_extras\Service;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheableMetadata;

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
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new MenuLinkTreeHandler.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityRepositoryInterface $entity_repository,
    CacheBackendInterface $cache_backend,
    LanguageManagerInterface $language_manager
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
    $this->cacheBackend = $cache_backend;
    $this->languageManager = $language_manager;
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
    // Cache the view mode per entity to avoid multiple lookups.
    static $view_mode_cache = [];

    if (isset($view_mode_cache[$entity->id()])) {
      return $view_mode_cache[$entity->id()];
    }

    $view_mode = 'default';
    if (!$entity->get('view_mode')->isEmpty()) {
      $value = $entity->get('view_mode')->first()->getValue();
      if (!empty($value['value'])) {
        $view_mode = $value['value'];
      }
    }

    $view_mode_cache[$entity->id()] = $view_mode;
    return $view_mode;
  }

  /**
   * {@inheritdoc}
   */
  public function getMenuLinkItemContent(MenuLinkContentInterface $entity, $menu_level = NULL, $show_item_link = FALSE) {
    // Get the current language.
    $current_language = $this->languageManager->getCurrentLanguage()->getId();

    // Include the current language and menu level in the cache ID for better granularity.
    $cache_id = 'menu_link_content:' . $entity->id() . ':' . $current_language . ':' . ($menu_level ?? 'null');

    // Add entity-specific cache tags to ensure proper invalidation.
    $cache_tags = [
      'menu_link_content:' . $entity->id(),
      'menu_link_content_view',
      'menu:' . $entity->getMenuName(),
    ];

    if ($cache = $this->cacheBackend->get($cache_id)) {
      return $cache->data;
    }

    // Check if the entity has a translation in the current language.
    if ($entity->hasTranslation($current_language)) {
      $entity = $entity->getTranslation($current_language);
    }

    // Build the render array for this menu link.
    $view_builder = $this->entityTypeManager->getViewBuilder('menu_link_content');
    $view_mode = $entity->id() ? $this->getMenuLinkContentViewMode($entity) : 'default';
    $render_output = $view_builder->view($entity, $view_mode);

    // Build the entity view ourselves and unset the #pre_render so that it
    // doesn't run twice later on, when rendered.
    // This gives us access to all fields immediately in the menu template.
    $render_output = $view_builder->build($render_output);
    array_pop($render_output['#pre_render']);

    // Preserve cache metadata but enhance it with our specific tags.
    if (!isset($render_output['#cache'])) {
      $render_output['#cache'] = [];
    }

    if (!isset($render_output['#cache']['tags'])) {
      $render_output['#cache']['tags'] = [];
    }

    $render_output['#cache']['tags'] = array_merge($render_output['#cache']['tags'], $cache_tags);
    $render_output['#cache']['contexts'][] = 'languages:language_interface';
    $render_output['#cache']['max_age'] = 43200; // 12 hours instead of permanent

    // Add other properties.
    $render_output['#show_item_link'] = $show_item_link;
    if (!is_null($menu_level)) {
      $render_output['#menu_level'] = $menu_level;
    }

    // Store in cache with proper tags for invalidation.
    $cache_metadata = CacheableMetadata::createFromRenderArray($render_output);
    $this->cacheBackend->set(
      $cache_id,
      $render_output,
      time() + 43200, // 12 hours instead of permanent
      $cache_metadata->getCacheTags()
    );

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
      $entity_ids = [];
      $entity_map = [];
      $current_language = $this->languageManager->getCurrentLanguage()->getId();

      // First pass: Collect all entity IDs (including from nested levels to reduce recursion)
      $this->collectEntityIds($items, $entity_ids);

      // Load all entities in a single call
      if (!empty($entity_ids)) {
          $entities = $this->entityTypeManager
              ->getStorage('menu_link_content')
              ->loadMultiple($entity_ids);

          // Map entities by their IDs
          foreach ($entities as $entity) {
              // Pre-translate entities to current language if available
              if ($entity->hasTranslation($current_language)) {
                  $entity = $entity->getTranslation($current_language);
              }
              $entity_map[$entity->id()] = $entity;
          }
      }

      // Second pass: Process items with loaded entities
      foreach ($items as &$item) {
          $content = [];
          if (isset($item['original_link'])) {
              $metadata = $item['original_link']->getMetaData();
              $entity_id = $metadata['entity_id'] ?? NULL;
              $content['#item'] = $item;
              $content['entity'] = $entity_id && isset($entity_map[$entity_id]) ? $entity_map[$entity_id] : NULL;

              // Only process content if entity exists
              if ($content['entity']) {
                  $content['content'] = $this->getMenuLinkItemContent($content['entity'], $menu_level, $show_item_link);

                  // Add cache metadata
                  $cacheMetadata = (new CacheableMetadata())
                    ->addCacheContexts(['route.menu_active_trails:' . $menu_name])
                    ->addCacheTags(['menu:' . $menu_name]);
                  $cacheMetadata->applyTo($content['content']);

                  $content['menu_level'] = $menu_level;
              } else {
                  $content['content'] = NULL;
                  $content['menu_level'] = $menu_level;
              }
          }

          // Process subitems.
          if (!empty($item['below'])) {
              $content['content']['children']['#items'] = $this->processMenuLinkTree($item['below'], $menu_name, $menu_level, $show_item_link);
              $content['content']['children']['#theme'] = 'menu_levels';
              $content['content']['children']['#menu_name'] = $menu_name;
              $content['content']['children']['#menu_level'] = $menu_level + 1;

              // Add cache metadata for children
              $cacheMetadata = (new CacheableMetadata())
                ->addCacheContexts(['route.menu_active_trails:' . $menu_name])
                ->addCacheTags(['menu:' . $menu_name]);
              $cacheMetadata->applyTo($content['content']['children']);
          }

          $item = array_merge($item, $content);
      }

      return $items;
  }

  /**
   * Helper method to collect all entity IDs from a menu tree, including nested items.
   *
   * @param array $items
   *   The menu items.
   * @param array $entity_ids
   *   Reference to the array where entity IDs will be collected.
   */
  protected function collectEntityIds(array $items, array &$entity_ids) {
      foreach ($items as $item) {
          if (isset($item['original_link'])) {
              $metadata = $item['original_link']->getMetaData();
              if (!empty($metadata['entity_id'])) {
                  $entity_ids[] = $metadata['entity_id'];
              }
          }

          // Recursively collect entity IDs from nested levels
          if (!empty($item['below'])) {
              $this->collectEntityIds($item['below'], $entity_ids);
          }
      }
  }

}
