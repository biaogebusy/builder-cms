<?php

namespace Drupal\admin_toolbar_tools;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\LocalTaskManagerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Admin Toolbar Tools helper service.
 */
class AdminToolbarToolsHelper {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The local task manger.
   *
   * @var \Drupal\Core\Menu\LocalTaskManagerInterface
   *   The local task manager menu.
   */
  protected $localTaskManager;

  /**
   * The route match interface.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   *   The route match.
   */
  protected $routeMatch;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Create an AdminToolbarToolsHelper object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Menu\LocalTaskManagerInterface $local_task_manager
   *   The local task manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LocalTaskManagerInterface $local_task_manager, RouteMatchInterface $route_match, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->localTaskManager = $local_task_manager;
    $this->routeMatch = $route_match;
    $this->configFactory = $config_factory;
  }

  /**
   * Generate the toolbar tab and item for primary local tasks.
   *
   * @return array<string, mixed>
   *   The toolbar item render array.
   */
  public function buildLocalTasksToolbar() {
    $build = [];

    $config = $this->configFactory->get('admin_toolbar_tools.settings');
    $cacheability = CacheableMetadata::createFromObject($config);

    if ($config->get('show_local_tasks')) {
      $local_tasks = $this->localTaskManager->getLocalTasks($this->routeMatch->getRouteName());
      $cacheability = $cacheability->merge($local_tasks['cacheability']);
      $cacheability = $cacheability->merge(CacheableMetadata::createFromObject($this->localTaskManager));

      if (!empty($local_tasks['tabs'])) {
        $local_task_links = [
          '#theme' => 'links',
          '#links' => [],
          '#attributes' => [
            'class' => ['toolbar-menu'],
          ],
        ];
        // Sort the links by weight.
        Element::children($local_tasks['tabs'], TRUE);
        // Only show the accessible local tasks.
        foreach (Element::getVisibleChildren($local_tasks['tabs']) as $task) {
          $local_task_links['#links'][$task] = $local_tasks['tabs'][$task]['#link'];
          if (isset($local_tasks['tabs'][$task]['#active']) && $local_tasks['tabs'][$task]['#active']) {
            $local_task_links['#links'][$task]['attributes']['class'][] = 'is-active';
          }
        }

        $build = [
          '#type' => 'toolbar_item',
          '#wrapper_attributes' => [
            'class' => ['local-tasks-toolbar-tab'],
          ],
          // Put it after contextual toolbar item so when float right is applied
          // local tasks item will be first.
          '#weight' => 10,
          'tab' => [
            // We can't use #lazy_builder here because
            // ToolbarItem::preRenderToolbarItem will insert #attributes before
            // lazy_builder callback and this will produce Exception.
            // This means that for now we always render Local Tasks item even
            // when the tray is empty.
            '#type' => 'link',
            '#title' => $this->t('Local Tasks'),
            '#url' => Url::fromRoute('<none>'),
            '#attributes' => [
              'class' => [
                'toolbar-icon',
                'toolbar-icon-local-tasks',
              ],
            ],
          ],
          'tray' => [
            'local_tasks' => $local_task_links,
          ],
          '#attached' => ['library' => ['admin_toolbar_tools/toolbar.icon']],
        ];
      }
    }

    $cacheability->applyTo($build);
    return $build;
  }

  /**
   * Gets a list of content entities.
   *
   * @return array<string, mixed>
   *   An array of metadata about content entities.
   */
  public function getBundleableEntitiesList() {
    $entity_types = $this->entityTypeManager->getDefinitions();
    $content_entities = [];
    foreach ($entity_types as $key => $entity_type) {
      if ($entity_type->getBundleEntityType() && ($entity_type->get('field_ui_base_route') != '')) {
        $content_entities[$key] = [
          'content_entity' => $key,
          'content_entity_bundle' => $entity_type->getBundleEntityType(),
        ];
      }
    }
    return $content_entities;
  }

  /**
   * Gets an array of entity types that should trigger a menu rebuild.
   *
   * @return array<int, mixed>
   *   An array of entity machine names.
   */
  public function getRebuildEntityTypes() {
    $types = ['menu'];
    $content_entities = $this->getBundleableEntitiesList();
    $types = array_merge($types, array_column($content_entities, 'content_entity_bundle'));
    return $types;
  }

}
