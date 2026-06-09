<?php

namespace Drupal\ai\Controller;

use Drupal\ai\AiToolsLibraryState;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Contains methods for tools library pages.
 */
class ToolsLibrary extends ControllerBase {

  /**
   * The ai tools ui builder.
   *
   * @var \Drupal\ai\Service\AiToolsLibraryUiBuilderInterface
   */
  protected $aiToolsUiBuilder;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->aiToolsUiBuilder = $container->get('ai.tools_library.ui_builder');
    return $instance;
  }

  /**
   * Tools library overview.
   *
   * @return array
   *   The render array for tools overview page.
   */
  public function overview(?AiToolsLibraryState $state = NULL) {
    return $this->aiToolsUiBuilder->buildUi($state);
  }

}
