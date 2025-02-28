<?php

namespace Drupal\gin;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Theme\ThemeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Service to handle content form overrides.
 */
class GinContentFormHelper implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The current user object.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * GinContentFormHelper constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   */
  public function __construct(AccountInterface $current_user, ModuleHandlerInterface $module_handler, RouteMatchInterface $route_match, ThemeManagerInterface $theme_manager) {
    $this->currentUser = $current_user;
    $this->moduleHandler = $module_handler;
    $this->routeMatch = $route_match;
    $this->themeManager = $theme_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('module_handler'),
      $container->get('current_route_match'),
      $container->get('theme.manager'),
    );
  }

  /**
   * Add some major form overrides.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $form_id
   *   The form id.
   *
   * @see hook_form_alter()
   */
  public function formAlter(array &$form, FormStateInterface $form_state, $form_id) {
    // Are we on an edit form?
    if (!$this->isContentForm($form, $form_state, $form_id)) {
      return;
    }

    // Provide a default meta form element if not already provided.
    // @see NodeForm::form()
    $form['advanced']['#attributes']['class'][] = 'entity-meta';
    if (!isset($form['meta'])) {
      $form['meta'] = [
        '#type' => 'container',
        '#group' => 'advanced',
        '#weight' => -10,
        '#title' => $this->t('Status'),
        '#attributes' => ['class' => ['entity-meta__header']],
        '#tree' => TRUE,
        '#access' => TRUE,
      ];
    }

    // Ensure correct settings for advanced, meta and revision form elements.
    $form['advanced']['#type'] = 'container';
    $form['advanced']['#accordion'] = TRUE;
    $form['meta']['#type'] = 'container';
    $form['meta']['#access'] = TRUE;

    $form['revision_information']['#type'] = 'container';
    $form['revision_information']['#group'] = 'meta';
    $form['revision_information']['#attributes']['class'][] = 'entity-meta__revision';

    // Action buttons.
    if (isset($form['actions'])) {
      if (isset($form['actions']['preview'])) {
        // Put Save after Preview.
        $save_weight = $form['actions']['preview']['#weight'] ? $form['actions']['preview']['#weight'] + 1 : 11;
        $form['actions']['submit']['#weight'] = $save_weight;
      }

      // Move entity_save_and_addanother_node after preview.
      if (isset($form['actions']['entity_save_and_addanother_node'])) {
        // Put Save after Preview.
        $save_weight = $form['actions']['entity_save_and_addanother_node']['#weight'];
        $form['actions']['preview']['#weight'] = $save_weight - 1;
      }

      // Create gin_actions group.
      $form['gin_actions'] = [
        '#type' => 'container',
        '#weight' => -1,
        '#multilingual' => TRUE,
        '#attributes' => [
          'class' => [
            'gin-sticky',
          ],
        ],
      ];
      // Assign status to gin_actions.
      $form['status']['#group'] = 'gin_actions';

      // Move all actions over.
      $form['gin_actions']['actions'] = ($form['actions']) ?? [];
      $form['gin_actions']['actions']['#weight'] = 130;

      // Now let's just remove delete, as we'll move that over to gin_sidebar.
      unset($form['gin_actions']['actions']['delete']);
      unset($form['gin_actions']['actions']['delete_translation']);

      // Add sidebar toggle.
      $hide_panel = t('Hide sidebar panel');
      $form['gin_actions']['gin_sidebar_toggle'] = [
        '#markup' => '<a href="#toggle-sidebar" class="meta-sidebar__trigger trigger" role="button" title="' . $hide_panel . '" aria-controls="gin_sidebar"><span class="visually-hidden">' . $hide_panel . '</span></a>',
        '#weight' => '999',
      ];
      $form['#attached']['library'][] = 'gin/sidebar';

      // Create gin_sidebar group.
      $form['gin_sidebar'] = [
        '#group' => 'meta',
        '#type' => 'container',
        '#weight' => 99,
        '#multilingual' => TRUE,
        '#attributes' => [
          'class' => [
            'gin-sidebar',
          ],
        ],
      ];
      // Copy footer over.
      $form['gin_sidebar']['footer'] = ($form['footer']) ?? [];
      // Copy actions.
      $form['gin_sidebar']['actions'] = [];
      $form['gin_sidebar']['actions']['#type'] = ($form['actions']['#type']) ?? [];
      // Copy delete action.
      $form['gin_sidebar']['actions']['delete'] = ($form['actions']['delete']) ?? [];
      // Copy delete_translation action.
      if (isset($form['actions']['delete_translation'])) {
        $form['gin_sidebar']['actions']['delete_translation'] = ($form['actions']['delete_translation']) ?? [];
        $form['gin_sidebar']['actions']['delete_translation']['#attributes']['class'][] = 'button--danger';
        $form['gin_sidebar']['actions']['delete_translation']['#attributes']['class'][] = 'action-link';
      }

      // Sidebar close button.
      $close_sidebar_translation = t('Close sidebar panel');
      $form['gin_sidebar']['gin_sidebar_close'] = [
        '#markup' => '<a href="#close-sidebar" class="meta-sidebar__close trigger" role="button" title="' . $close_sidebar_translation . '"><span class="visually-hidden">' . $close_sidebar_translation . '</span></a>',
      ];

      $form['gin_sidebar_overlay'] = [
        '#markup' => '<div class="meta-sidebar__overlay trigger"></div>',
      ];
    }

    // Specify necessary node form theme and library.
    // @see claro_form_node_form_alter
    $form['#theme'] = ['node_edit_form'];
    // Attach libraries.
    $form['#attached']['library'][] = 'claro/node-form';
    $form['#attached']['library'][] = 'gin/edit_form';

    // Add a class that allows the logic in edit_form.js to identify the form.
    $form['#attributes']['class'][] = 'gin-node-edit-form';

    // If not logged in hide changed and author node info on add forms.
    $not_logged_in = $this->currentUser->isAnonymous();
    $route = $this->routeMatch->getRouteName();

    if ($not_logged_in && $route == 'node.add') {
      unset($form['meta']['changed']);
      unset($form['meta']['author']);
    }

  }

  /**
   * Check if we´re on a content edit form.
   *
   * _gin_is_content_form() is replaced by
   * \Drupal::classResolver(GinContentFormHelper::class)->isContentForm().
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $form_id
   *   The form id.
   */
  public function isContentForm(array $form = NULL, FormStateInterface $form_state = NULL, $form_id = '') {
    $is_content_form = FALSE;

    // Get route name.
    $route_name = $this->routeMatch->getRouteName();

    // Routes to include.
    $route_names = [
      'node.add',
      'entity.node.content_translation_add',
      'entity.node.content_translation_edit',
      'quick_node_clone.node.quick_clone',
      'entity.node.edit_form',
    ];

    $additional_routes = $this->moduleHandler->invokeAll('gin_content_form_routes');
    $route_names = array_merge($additional_routes, $route_names);
    $this->moduleHandler->alter('gin_content_form_routes', $route_names);
    $this->themeManager->alter('gin_content_form_routes', $route_names);

    if (
      in_array($route_name, $route_names, TRUE) ||
      ($form_state && ($form_state->getBuildInfo()['base_form_id'] ?? NULL) === 'node_form') ||
      ($route_name === 'entity.group_content.create_form' && substr($this->routeMatch->getParameter('plugin_id'), 0, 11) === "group_node:") ||
      ($route_name === 'entity.group_relationship.create_form' && substr($this->routeMatch->getParameter('plugin_id'), 0, 11) === "group_node:")
    ) {
      $is_content_form = TRUE;
    }

    // Forms to exclude.
    // If media library widget, don't use new content edit form.
    // gin_preprocess_html is not triggered here, so checking
    // the form id is enough.
    $form_ids_to_ignore = [
      'media_library_add_form_',
      'views_form_media_library_widget_',
      'views_exposed_form',
      'date_recur_modular_sierra_occurrences_modal',
      'date_recur_modular_sierra_modal',
    ];

    foreach ($form_ids_to_ignore as $form_id_to_ignore) {
      if ($form_id && strpos($form_id, $form_id_to_ignore) !== FALSE) {
        $is_content_form = FALSE;
      }
    }

    return $is_content_form;
  }

}
