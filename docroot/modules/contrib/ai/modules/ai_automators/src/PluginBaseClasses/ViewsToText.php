<?php

namespace Drupal\ai_automators\PluginBaseClasses;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use League\HTMLToMarkdown\HtmlConverter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for taking a Views and storing it as text.
 */
abstract class ViewsToText extends RuleBase {

  use DependencySerializationTrait;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The render service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->moduleHandler = $container->get('module_handler');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->currentUser = $container->get('current_user');
    $instance->renderer = $container->get('renderer');
    $instance->token = $container->get('token');
    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function needsPrompt() {
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function advancedMode() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function extraFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []) {
    $form = parent::extraFormFields($entity, $fieldDefinition, $formState, $defaultValues);

    // Add a warning about permissions.
    $form['automator_permissions_warning'] = [
      '#type' => 'markup',
      '#markup' => $this->t('⚠️ Note that this will save the data found in this views and that any user that has access to view this field will see the content, independent on views permissions. Only the user that has access to the Views can however trigger it.'),
      '#weight' => 10,
    ];

    // Get all the views the user has access to.
    $views = [];
    /** @var \Drupal\views\Entity\View $view */
    foreach ($this->entityTypeManager->getStorage('view')->loadMultiple() as $view) {
      if ($view->access('view')) {
        // Get the display names.
        foreach ($view->get('display') as $display_id => $display) {
          $views[$view->id() . '__' . $display_id] = $view->label() . " (Display: " . $display['display_title'] . ")";
        }
      }
    }

    $default_view = $formState->getValue('automator_view') ?? $defaultValues['automator_view'] ?? NULL;

    $form['automator_view'] = [
      '#type' => 'select',
      '#title' => $this->t('View'),
      '#description' => $this->t('Select the view to use as input.'),
      '#options' => $views,
      '#default_value' => $default_view,
      '#ajax' => [
        'callback' => [$this, 'ajaxGetViewArgumentsFilters'],
        'wrapper' => 'advanced',
        'event' => 'change',
      ],
    ];

    $form['automator_args'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'advanced'],
      '#tree' => TRUE,
    ];

    $form['automator_args']['arguments'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Arguments'),
      '#description' => $this->t('Add arguments to the view.'),
    ];

    $form['automator_args']['exposed_filters'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Exposed Filters'),
      '#description' => $this->t('Add exposed filters to the view.'),
    ];

    if ($default_view) {
      $parts = explode('__', $default_view);
      $viewId = $parts[0];
      $displayId = $parts[1];
      $view = Views::getView($viewId);
      $view->setDisplay($displayId);
      // Get the arguments.
      foreach ($view->display_handler->getHandlers('argument') as $argument) {
        $form['automator_args']['arguments'][$argument->options['id']] = [
          '#type' => 'textfield',
          '#title' => $this->t('Argument: @name', ['@name' => $argument->options['id']]),
          '#description' => $this->t('The value for the argument. You may use tokens, a hardcoded value or leave it empty.'),
          '#default_value' => $defaultValues['automator_args']['arguments'][$argument->options['id']] ?? '',
        ];
      }

      // Get the exposed filters.
      foreach ($view->display_handler->getHandlers('filter') as $filter) {
        // Make sure the filter is exposed.
        if (empty($filter->options['expose']['identifier'])) {
          continue;
        }
        $form['automator_args']['exposed_filters'][$filter->options['expose']['identifier']] = [
          '#type' => 'textfield',
          '#title' => $this->t('Filter: @name', ['@name' => $filter->options['expose']['identifier']]),
          '#description' => $this->t('The value for the filter. You may use tokens, a hardcoded value or leave it empty'),
          '#default_value' => $defaultValues['automator_args']['exposed_filters'][$filter->options['expose']['identifier']] ?? '',
        ];
      }

    }

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []) {
    $form = parent::extraAdvancedFormFields($entity, $fieldDefinition, $formState, $defaultValues);

    // Allow html to markdown conversion.
    $form['automator_html_to_markdown'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Convert HTML to Markdown'),
      '#description' => $this->t('Converts HTML to Markdown.'),
      '#default_value' => $defaultValues['automator_html_to_markdown'] ?? FALSE,
    ];

    return $form;
  }

  /**
   * Ajax method to get all the arguments and filters for a view.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   */
  public function ajaxGetViewArgumentsFilters(array $form, FormStateInterface $formState) {
    $formState->setRebuild(TRUE);
    return $form['advanced'];
  }

  /**
   * {@inheritDoc}
   */
  public function ruleIsAllowed(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    // The user must have access to administrate views and Views enabled.
    return $this->currentUser->hasPermission('administer views') && $this->moduleHandler->moduleExists('views');
  }

  /**
   * {@inheritDoc}
   */
  public function checkIfEmpty(array $value, array $automatorConfig = []) {
    return $value;
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    $viewsParts = explode('__', $automatorConfig['view']);
    $viewId = $viewsParts[0];
    $displayId = $viewsParts[1];
    // Load the view.
    $view = Views::getView($viewId);
    $view->setDisplay($displayId);
    // Check if the user has access to the view.
    if (!$this->userHasAccessToView($view, $displayId)) {
      return [
        'value' => 'No access to view.',
      ];
    }
    // If arguments are set, set them.
    if (!empty($automatorConfig['args']['arguments'])) {
      foreach ($automatorConfig['args']['arguments'] as $argument => $value) {
        // Run it through tokens.
        $value = $this->token->replace($value, [
          'entity' => $entity,
          'user' => $this->currentUser,
        ]);
        $view->setArguments([$argument => $value]);
      }
    }
    // If exposed filters are set, set them.
    if (!empty($automatorConfig['args']['exposed_filters'])) {
      foreach ($automatorConfig['args']['exposed_filters'] as $filter => $value) {
        $value = $this->token->replace($value, [
          'entity' => $entity,
          'user' => $this->currentUser,
        ]);
        $view->setExposedInput([$filter => $value]);
      }
    }
    $view->initHandlers();
    $view->preExecute();
    $view->execute();
    $result = $view->buildRenderable($displayId);
    $output = $this->renderer->renderInIsolation($result);
    if ($automatorConfig['html_to_markdown']) {
      $converter = new HtmlConverter(['strip_tags' => TRUE]);
      $output = $converter->convert($output);
    }
    return [
      'value' => $output,
    ];
  }

  /**
   * Check if the user has access to the view.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view to check.
   * @param string $displayId
   *   The display id.
   *
   * @return bool
   *   If the user has access.
   */
  protected function userHasAccessToView(ViewExecutable $view, string $displayId) {
    return $view->access($displayId, $this->currentUser);
  }

}
