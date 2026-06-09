<?php

namespace Drupal\ai\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\AiVdbProviderPluginManager;
use Drupal\system\SystemManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the provider setup list.
 */
class ProviderSetupList extends ControllerBase {

  /**
   * Constructs a new ProviderSetupList object.
   *
   * @param \Drupal\ai\AiVdbProviderPluginManager $vdbProviderManager
   *   The AI vector database provider service.
   * @param \Drupal\ai\AiProviderPluginManager $aiProviderManager
   *   The AI provider service.
   * @param \Drupal\system\SystemManager $systemManager
   *   The system manager service.
   * @param \Drupal\Core\Path\CurrentPathStack $currentPath
   *   The current path.
   */
  public function __construct(
    protected AiVdbProviderPluginManager $vdbProviderManager,
    protected AiProviderPluginManager $aiProviderManager,
    protected SystemManager $systemManager,
    protected CurrentPathStack $currentPath,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai.vdb_provider'),
      $container->get('ai.provider'),
      $container->get('system.manager'),
      $container->get('path.current'),
    );
  }

  /**
   * Display the provider setup list.
   *
   * @return array
   *   A render array suitable for rendering the admin interface.
   */
  public function list() {
    // Check special cases based on the current path.
    switch ($this->currentPath->getPath()) {
      case '/admin/config/ai/vdb_providers':
        if (empty($this->vdbProviderManager->getProviders())) {
          return [
            '#markup' => $this->t('No vector database provider is configured. Please <a href=":link" target="_blank">configure a provider</a> to use this feature.', [
              ':link' => 'https://project.pages.drupalcode.org/ai/latest/providers/matris/',
            ]),
            '#allowed_tags' => ['a'],
          ];
        }
        break;

      case '/admin/config/ai/providers':
        if (empty($this->aiProviderManager->getDefinitions())) {
          return [
            '#markup' => $this->t('No AI provider is configured. Please <a href=":link" target="_blank">configure a provider</a> to use this feature.', [
              ':link' => 'https://project.pages.drupalcode.org/ai/latest/providers/matris/',
            ]),
            '#allowed_tags' => ['a'],
          ];
        }
        break;
    }
    return $this->systemManager->getBlockContents();
  }

}
