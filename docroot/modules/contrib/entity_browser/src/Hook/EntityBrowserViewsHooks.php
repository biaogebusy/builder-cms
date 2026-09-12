<?php

namespace Drupal\entity_browser\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for entity_browser.
 */
class EntityBrowserViewsHooks {
  use StringTranslationTrait;

  /**
   * Constructs a new EntityBrowserViewsHooks object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Implements hook_views_data_alter().
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(&$data): void {
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
      if ($base_table = $entity_type->getBaseTable()) {
        $entity_keys = $entity_type->get('entity_keys');
        $data[$base_table]['entity_browser_select'] = [
          'title' => $this->t('Entity browser bulk select form'),
          'help' => $this->t('Add a form element that lets you use a view as a base to select entities in entity browser.'),
          'field' => [
            'id' => 'entity_browser_select',
            'real field' => $entity_keys['id'],
          ],
        ];
        if (!empty($entity_keys['bundle'])) {
          $data[$base_table]['entity_browser_bundle'] = [
            'title' => $this->t('Entity Browser Target Bundles'),
            'filter' => [
              'id' => 'entity_browser_bundle',
              'real field' => $entity_keys['bundle'],
            ],
          ];
        }
      }
    }
    if ($this->moduleHandler->moduleExists('search_api')) {
      /** @var \Drupal\search_api\IndexInterface $index */
      foreach ($this->entityTypeManager->getStorage('search_api_index')->loadMultiple() as $index) {
        $key = 'search_api_index_' . $index->id();
        $data[$key]['entity_browser_select'] = [
          'title' => $this->t('Entity browser bulk select form for Search API views'),
          'help' => $this->t('Add a form element that lets you use a view as a base to select entities in entity browser.'),
          'field' => [
            'id' => 'entity_browser_search_api_select',
            'real field' => 'id',
          ],
        ];
      }
    }
  }

  /**
   * Implements hook_views_plugins_display_alter().
   */
  #[Hook('views_plugins_display_alter')]
  public function viewsPluginsDisplayAlter(array &$plugins): void {
    $plugins['entity_browser']['entity_browser_display'] = TRUE;
  }

}
