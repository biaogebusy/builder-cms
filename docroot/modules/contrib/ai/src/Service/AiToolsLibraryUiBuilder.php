<?php

namespace Drupal\ai\Service;

use Drupal\ai\AiToolsLibraryState;
use Drupal\ai\Form\AiToolsLibrarySelectForm;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The Ai Tools library UI Builder service.
 */
class AiToolsLibraryUiBuilder implements AiToolsLibraryUiBuilderInterface {

  use StringTranslationTrait;

  /**
   * The function group plugin manager.
   *
   * @var \Drupal\ai\Service\FunctionCalling\FunctionGroupPluginManager
   */
  protected PluginManagerInterface $functionGroupPluginManager;

  /**
   * The function call (tools) plugin manager.
   *
   * @var \Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager
   */
  protected PluginManagerInterface $functionCallPluginManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The currently active request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected FormBuilderInterface $formBuilder;

  /**
   * Constructs AiToolsLibraryUiBuilder object.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $function_group_plugin_manager
   *   The plugin manager for function groups.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $function_call_plugin_manager
   *   The plugin manager for function tools.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form builder service.
   */
  public function __construct(PluginManagerInterface $function_group_plugin_manager, PluginManagerInterface $function_call_plugin_manager, ModuleHandlerInterface $module_handler, RequestStack $request_stack, FormBuilderInterface $formBuilder) {
    $this->functionGroupPluginManager = $function_group_plugin_manager;
    $this->functionCallPluginManager = $function_call_plugin_manager;
    $this->moduleHandler = $module_handler;
    $this->request = $request_stack->getCurrentRequest();
    $this->formBuilder = $formBuilder;
  }

  /**
   * {@inheritdoc}
   */
  public function buildUi(?AiToolsLibraryState $state = NULL) {
    if (!$state) {
      $state = AiToolsLibraryState::fromRequest($this->request);
    }
    // When navigating to a group type through the vertical tabs, we only want
    // to load the changed library content. This is not only more efficient, but
    // also provides a more accessible user experience for screen readers.
    if ($state->get('ai_tools_library_content') === '1') {
      return $this->buildAiToolsList($state);
    }
    return [
      '#theme' => 'ai_tools_library_wrapper',
      '#attributes' => [
        'id' => 'ai-tools-library-wrapper',
      ],
      '#menu' => $this->buildAiToolsMenu($state),
      '#content' => $this->buildAiToolsList($state),
      '#attached' => [
        'library' => ['ai/tools_library'],
      ],
    ];
  }

  /**
   * Get the menu for the ai tools library.
   *
   * @return array
   *   The render array for the ai tools menu.
   */
  protected function buildAiToolsMenu(AiToolsLibraryState $state) {
    // @todo Add a class to the li element.
    //   https://www.drupal.org/project/drupal/issues/3029227
    $menu = [
      '#theme' => 'links__ai_tools_library_menu',
      '#links' => [],
      '#attributes' => [
        'class' => ['js-ai-tools-library-menu', 'ai-tools-library-menu'],
      ],
    ];
    $link_state = AiToolsLibraryState::create($state->getOpenerId(), $state->getAllowedGroupIds(), '_all', $state->getOpenerParameters());
    $link_state->set('ai_tools_library_content', 1);
    $menu['#links']['ai-tools-library-menu-_all'] = [
      'title' => $this->t('All'),
      'url' => Url::fromRoute('ai.tools_library', [], ['query' => $link_state->all()]),
      'attributes' => [
        'role' => 'button',
        'data-title' => $this->t('All'),
      ],
      'wrapper_attributes' => [
        'class' => ['ai-tools-library-menu__item'],
      ],
      'weight' => -1000,
    ];
    $selected_group_id = $state->getSelectedGroupId();
    foreach ($this->functionGroupPluginManager->getDefinitions() as $group_id => $group) {
      $link_state = AiToolsLibraryState::create($state->getOpenerId(), $state->getAllowedGroupIds(), $group_id, $state->getOpenerParameters());
      // Add the 'ai_tools_library_content' parameter so the response will
      // contain only the updated content for the tab. @see self::buildUi()
      $link_state->set('ai_tools_library_content', 1);
      $title = $group['group_name'];
      $display_title = [
        '#markup' => $this->t('<span class="visually-hidden">Show </span>@title<span class="visually-hidden"> tools</span>', ['@title' => $title]),
      ];
      if ($group_id === $selected_group_id) {
        $display_title = [
          '#markup' => $this->t('<span class="visually-hidden">Show </span>@title<span class="visually-hidden"> tools</span><span class="active-tab visually-hidden"> (selected)</span>', ['@title' => $title]),
        ];
      }

      $menu['#links']['ai-tools-library-menu-' . $group_id] = [
        'title' => $display_title,
        'url' => Url::fromRoute('ai.tools_library', [], ['query' => $link_state->all()]),
        'attributes' => [
          'role' => 'button',
          'data-title' => $title,
        ],
        'wrapper_attributes' => [
          'class' => ['ai-tools-library-menu__item'],
        ],
        'weight' => $group['weight'],
      ];
    }
    uasort($menu['#links'], function ($a, $b) {
      return $a['weight'] <=> $b['weight'];
    });
    // Set the active menu item.
    $menu['#links']['ai-tools-library-menu-' . $selected_group_id]['attributes']['class'][] = 'active';

    return $menu;
  }

  /**
   * Build the ai tools library content area.
   *
   * @return array
   *   The render array for the ai tools library.
   */
  protected function buildAiToolsList(AiToolsLibraryState $state) {
    return [
      '#type' => 'container',
      '#theme_wrappers' => [
        'container__ai_tools_library',
      ],
      '#attributes' => [
        'id' => 'ai-tools-library-content',
        'class' => ['ai-tools-library-content'],
      ],
      'view' => $this->buildAiToolsLibraryView($state),
    ];
  }

  /**
   * Builds AI Tools library.
   *
   * @param \Drupal\ai\AiToolsLibraryState $state
   *   The selected group.
   *
   * @return array
   *   The render array.
   */
  protected function buildAiToolsLibraryView(AiToolsLibraryState $state) {
    return $this->formBuilder->getForm(AiToolsLibrarySelectForm::class, $state);
  }

}
