<?php

namespace Drupal\ai_api_explorer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\ai_api_explorer\AiApiExplorerPluginManager;
use Drupal\system\SystemManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the provider setup list.
 */
class ExplorerSetupList extends ControllerBase {

  /**
   * Constructs a new ProviderSetupList object.
   *
   * @param \Drupal\system\SystemManager $systemManager
   *   The system manager service.
   * @param \Drupal\ai_api_explorer\AiApiExplorerPluginManager $aiApiExplorerManager
   *   The AI API Explorer plugin manager service.
   */
  public function __construct(
    protected SystemManager $systemManager,
    protected AiApiExplorerPluginManager $aiApiExplorerManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('system.manager'),
      $container->get('plugin.manager.ai_api_explorer'),
    );
  }

  /**
   * Display the provider setup list.
   *
   * @return array
   *   A render array suitable for rendering the admin interface.
   */
  public function list() {

    $explorers = $this->aiApiExplorerManager->getDefinitions();
    $has_active = FALSE;
    foreach ($explorers as $id => $explorer_definition) {
      $explorer = $this->aiApiExplorerManager->createInstance($id);
      if ($explorer->isActive()) {
        $has_active = TRUE;
        break;
      }
    }
    if (!$has_active) {
      return [
        '#markup' => $this->t('No API Explorer is configured because you are missing providers for it. Please <a href=":link" target="_blank">configure a provider</a> to use this feature.', [
          ':link' => 'https://project.pages.drupalcode.org/ai/latest/providers/matris/',
        ]),
        '#allowed_tags' => ['a'],
      ];
    }
    return $this->systemManager->getBlockContents();
  }

}
