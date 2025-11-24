<?php

namespace Drupal\panels_ipe\Form;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\ContextAwarePluginAssignmentTrait;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\panels\Form\PanelsStyleTrait;
use Drupal\panels\Plugin\DisplayVariant\PanelsDisplayVariant;
use Drupal\panels_ipe\PanelsIPEBlockRendererTrait;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a form for adding a block plugin temporarily using AJAX.
 *
 * Unlike most forms, this never saves a block plugin instance or persists it
 * from state to state. This is only for the initial addition to the Layout.
 */
class PanelsIPEBlockPluginForm extends FormBase {

  use ContextAwarePluginAssignmentTrait;

  use PanelsIPEBlockRendererTrait;
  use PanelsStyleTrait;

  /**
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $blockManager;

  /**
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * @var \Drupal\Core\TempStore\SharedTempStore
   */
  protected $tempStore;

  /**
   * The Panels storage manager.
   *
   * @var \Drupal\panels\Plugin\DisplayVariant\PanelsDisplayVariant
   */
  protected $panelsDisplay;

  /**
   * Constructs a new PanelsIPEBlockPluginForm.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $block_manager
   * @param \Drupal\Core\Plugin\Context\ContextHandlerInterface $context_handler
   * @param \Drupal\Core\Render\RendererInterface $renderer
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory $temp_store_factory
   */
  public function __construct(PluginManagerInterface $block_manager, ContextHandlerInterface $context_handler, RendererInterface $renderer, SharedTempStoreFactory $temp_store_factory) {
    $this->blockManager = $block_manager;
    $this->contextHandler = $context_handler;
    $this->renderer = $renderer;
    $this->tempStore = $temp_store_factory->get('panels_ipe');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.block'),
      $container->get('context.handler'),
      $container->get('renderer'),
      $container->get('tempstore.shared')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'panels_ipe_block_plugin_form';
  }

  /**
   * Builds a form that constructs a unsaved instance of a Block for the IPE.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $plugin_id
   *   The requested Block Plugin ID.
   * @param \Drupal\panels\Plugin\DisplayVariant\PanelsDisplayVariant $panels_display
   *   The current PageVariant ID.
   * @param string $uuid
   *   An optional Block UUID, if this is an existing Block.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $plugin_id = NULL, PanelsDisplayVariant $panels_display = NULL, $uuid = NULL) {
    // We require these default arguments.
    if (!$plugin_id || !$panels_display) {
      return FALSE;
    }

    // Save the panels display for later.
    $this->panelsDisplay = $panels_display;

    // Grab the current layout's regions.
    $regions = $panels_display->getRegionNames();

    // If $uuid is present, a block should exist.
    if ($uuid) {
      /** @var \Drupal\Core\Block\BlockBase $block_instance */
      $block_instance = $panels_display->getBlock($uuid);
    }
    else {
      // Create an instance of this Block plugin.
      /** @var \Drupal\Core\Block\BlockBase $block_instance */
      $block_instance = $this->blockManager->createInstance($plugin_id);
    }

    // Determine the current region.
    $block_config = $block_instance->getConfiguration();
    if (isset($block_config['region']) && isset($regions[$block_config['region']])) {
      $region = $block_config['region'];
    }
    else {
      $region = reset($regions);
    }

    // Some Block Plugins rely on the block_theme value to load theme settings.
    // @see \Drupal\system\Plugin\Block\SystemBrandingBlock::blockForm().
    $form_state->set('block_theme', $this->config('system.theme')->get('default'));

    // Wrap the form so that our AJAX submit can replace its contents.
    $form['#prefix'] = '<div id="panels-ipe-block-plugin-form-wrapper">';
    $form['#suffix'] = '</div>';

    // Add our various card wrappers.
    $form['flipper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['flipper'],
      ],
    ];

    $form['flipper']['front'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['front'],
      ],
    ];

    $form['flipper']['back'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['back'],
      ],
    ];

    $form['#attributes']['class'][] = 'flip-container';

    // Get the base configuration form for this block.
    $form['flipper']['front']['settings'] = $block_instance->buildConfigurationForm([], $form_state);
    $form['flipper']['front']['settings']['context_mapping'] = $this->addContextAssignmentElement($block_instance, $this->panelsDisplay->getContexts());
    $form['flipper']['front']['settings']['#tree'] = TRUE;

    if (!empty($_POST['currentPath'])) {
      $form['currentPath'] = ['#type' => 'hidden', '#value' => $_POST['currentPath']];
    }

    // Add the block ID, variant ID to the form as values.
    $form['plugin_id'] = ['#type' => 'value', '#value' => $plugin_id];
    $form['variant_id'] = ['#type' => 'value', '#value' => $panels_display->id()];
    $form['uuid'] = ['#type' => 'value', '#value' => $uuid];

    // Add a select list for region assignment.
    $form['flipper']['front']['settings']['region'] = [
      '#title' => $this->t('Region'),
      '#type' => 'select',
      '#options' => $regions,
      '#required' => TRUE,
      '#default_value' => $region,
    ];
    $form['flipper']['front']['settings'] += $this->getCssStyleForm($block_config, TRUE);

    // Add an add button, which is only used by our App.
    $form['submit'] = [
      '#type' => 'button',
      '#value' => $uuid ? $this->t('Update') : $this->t('Add'),
      '#ajax' => [
        'callback' => '::submitForm',
        'wrapper' => 'panels-ipe-block-plugin-form-wrapper',
        'method' => 'replace',
        'progress' => [
          'type' => 'throbber',
          'message' => '',
        ],
      ],
    ];

    // Add a preview button.
    $form['preview'] = [
      '#type' => 'button',
      '#value' => $this->t('Toggle Preview'),
      '#ajax' => [
        'callback' => '::submitPreview',
        'wrapper' => 'panels-ipe-block-plugin-form-wrapper',
        'method' => 'replace',
        'progress' => [
          'type' => 'throbber',
          'message' => '',
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $block_instance = $this->getBlockInstance($form_state);

    // Validate the block configuration form.
    $block_form_state = (new FormState())->setValues($form_state->getValue('settings'));
    $block_instance->validateConfigurationForm($form, $block_form_state);
    // Update the original form values.
    foreach ($block_form_state->getErrors() as $name => $error) {
      $form_state->setErrorByName($name, $error);
    }

    $form_state->setValue('settings', $block_form_state->getValues());
  }

  /**
   * Executes the block plugin's submit handlers.
   *
   * @param \Drupal\Core\Block\BlockPluginInterface $block_instance
   *   The block instance.
   * @param array $form
   *   The full form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The full form state.
   */
  protected function submitBlock(BlockPluginInterface $block_instance, array $form, FormStateInterface $form_state) {
    $block_form_state = (new FormState())->setValues($form_state->getValue('settings'));
    $block_instance->submitConfigurationForm($form['flipper']['front']['settings'], $block_form_state);
    if ($block_instance instanceof ContextAwarePluginInterface) {
      $block_instance->setContextMapping($block_form_state->getValue('context_mapping', []));
    }
    // Update the original form values.
    $form_state->setValue('settings', $block_form_state->getValues());
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Return early if there are any errors.
    if ($form_state->hasAnyErrors()) {
      return $form;
    }

    // Reload the panels display. If this is not done, the contexts are not
    // correctly populated after ajax updates. Not sure why this is.
    $panels_config = $this->panelsDisplay->getConfiguration();
    $panels_storage = \Drupal::service('panels.storage_manager');
    $this->panelsDisplay = $panels_storage->load($panels_config['storage_type'], $panels_config['storage_id']);

    // If a temporary configuration for this variant exists, use it.
    $temp_store_key = $this->panelsDisplay->getTempStoreId();
    if ($variant_config = $this->tempStore->get($temp_store_key)) {
      $this->panelsDisplay->setConfiguration($variant_config);
    }

    if ($form_state->getValue('currentPath')) {
      $contexts = array_merge($this->panelsDisplay->getContexts(), $this->getContextsForPath($form_state->getValue('currentPath')));
      $this->panelsDisplay->setContexts($contexts);
    }

    $block_instance = $this->getBlockInstance($form_state);

    // Submit the block configuration form.
    $this->submitBlock($block_instance, $form, $form_state);

    // Set the block region appropriately.
    $block_config = $block_instance->getConfiguration();
    $block_config['region'] = $form_state->getValue(['settings', 'region']);
    $block_config['css_classes'] = preg_split('/\s+/', trim($form_state->getValue([
      'settings',
      'style_settings',
      'css_classes',
    ])));
    $block_config['html_id'] = $form_state->getValue([
      'settings',
      'style_settings',
      'html_id',
    ]);
    $block_config['css_styles'] = $form_state->getValue([
      'settings',
      'style_settings',
      'css_styles',
    ]);

    // Determine if we need to update or add this block.
    if ($uuid = $form_state->getValue('uuid')) {
      $this->panelsDisplay->updateBlock($uuid, $block_config);
    }
    else {
      $uuid = $this->panelsDisplay->addBlock($block_config);
    }

    // Set the tempstore value.
    $this->tempStore->set($this->panelsDisplay->getTempStoreId(), $this->panelsDisplay->getConfiguration());

    // Assemble data required for our App.
    $build = $this->buildBlockInstance($block_instance, $this->panelsDisplay);

    // Bubble Block attributes to fix bugs with the Quickedit and Contextual
    // modules.
    $this->bubbleBlockAttributes($build);

    // Add our data attribute for the Backbone app.
    $build['#attributes']['data-block-id'] = $uuid;

    // Add CSS classes.
    foreach ($block_config['css_classes'] as $class) {
      $build['#attributes']['class'][] = Html::cleanCssIdentifier($class);
    }
    // Add HTML Id.
    if (!empty($block_config['html_id'])) {
      $build['#attributes']['id'] = Html::getId($block_config['html_id']);
    }
    // Add CSS styles.
    if (!empty($block_config['css_styles'])) {
      $build['#attributes']['style'] = $block_config['css_styles'];
    }

    $plugin_definition = $block_instance->getPluginDefinition();

    $block_model = [
      'uuid' => $uuid,
      'label' => $block_instance->label(),
      'id' => $block_instance->getPluginId(),
      'region' => $block_config['region'],
      'provider' => $block_config['provider'],
      'plugin_id' => $plugin_definition['id'],
      'html' => $this->renderer->render($build),
    ];

    $form['build'] = $build;

    // Add Block metadata and HTML as a drupalSetting.
    $form['#attached']['drupalSettings']['panels_ipe']['updated_block'] = $block_model;

    return $form;
  }

  /**
   * Previews our current Block configuration.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function submitPreview(array &$form, FormStateInterface $form_state) {
    // Return early if there are any errors.
    if ($form_state->hasAnyErrors()) {
      return $form;
    }

    if ($form_state->getValue('currentPath')) {
      $contexts = array_merge($this->panelsDisplay->getContexts(), $this->getContextsForPath($form_state->getValue('currentPath')));
      $this->panelsDisplay->setContexts($contexts);
    }

    // Get the Block instance.
    $block_instance = $this->getBlockInstance($form_state);

    // Submit the block configuration form.
    $this->submitBlock($block_instance, $form, $form_state);

    // Gather a render array for the block.
    $build = $this->buildBlockInstance($block_instance, $this->panelsDisplay);

    // Add CSS classes.
    $css_classes = preg_split('/\s+/', trim($form_state->getValue([
      'settings',
      'style_settings',
      'css_classes',
    ])));
    foreach ($css_classes as $class) {
      $build['#attributes']['class'][] = Html::cleanCssIdentifier($class);
    }
    // Add HTML Id.
    $html_id = $form_state->getValue(['settings', 'style_settings', 'html_id']);
    if (!empty($html_id)) {
      $build['#attributes']['id'] = Html::getId($html_id);
    }
    // Add CSS styles.
    $css_styles = $form_state->getValue([
      'settings',
      'style_settings',
      'css_styles',
    ]);
    if (!empty($css_styles)) {
      $build['#attributes']['style'] = $css_styles;
    }

    // Replace any nested form tags from the render array.
    $build['content']['#post_render'][] = function ($html, array $elements) {
      $search = ['<form', '</form>'];
      $replace = ['<div', '</div>'];
      return str_replace($search, $replace, $html);
    };

    // Add the preview to the backside of the card and inform JS that we need to
    // be flipped.
    $form['flipper']['back']['preview'] = $build;

    // Add a cleafix element to the end of the preview. This prevents overlaps
    // with nested float elements.
    $build['clearfix'] = [
      '#markup' => '<div class="clearfix"></div>',
    ];

    $form['#attached']['drupalSettings']['panels_ipe']['toggle_preview'] = TRUE;

    return $form;
  }

  /**
   * Loads or creates a Block Plugin instance suitable for rendering or testing.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Block\BlockPluginInterface
   *   The Block Plugin instance.
   */
  protected function getBlockInstance(FormStateInterface $form_state) {
    // If a UUID is provided, the Block should already exist.
    if ($uuid = $form_state->getValue('uuid')) {
      // If a temporary configuration for this variant exists, use it.
      $temp_store_key = $this->panelsDisplay->getTempStoreId();
      if ($variant_config = $this->tempStore->get($temp_store_key)) {
        $this->panelsDisplay->setConfiguration($variant_config);
      }

      // Load the existing Block instance.
      $block_instance = $this->panelsDisplay->getBlock($uuid);
    }
    else {
      // Create an instance of this Block plugin.
      /** @var \Drupal\Core\Block\BlockBase $block_instance */
      $block_instance = $this->blockManager->createInstance($form_state->getValue('plugin_id'));
    }

    return $block_instance;
  }

  /**
   * Retrieve additional context values based on the path.
   *
   * @param string $path
   *   The path to the page being edited using IPE.
   *
   * @return \Drupal\Core\Plugin\Context\Context[]
   *   The extracted contexts.
   */
  protected function getContextsForPath($path) {
    $request = Request::create('/' . $path);
    $router = \Drupal::service('router.no_access_checks');
    $result = $router->matchRequest($request);

    $route = $result['_route_object'];
    $page = $result['page_manager_page'];

    $contexts = [];
    if ($route && $route_contexts = $route->getOption('parameters')) {
      foreach ($route_contexts as $route_context_name => $route_context) {
        // Skip this parameter.
        if ($route_context_name == 'page_manager_page_variant' || $route_context_name == 'page_manager_page') {
          continue;
        }

        $parameter = $page->getParameter($route_context_name);
        $context_name = !empty($parameter['label']) ? $parameter['label'] : $this->t('{@name} from route', ['@name' => $route_context_name]);
        if ($request->attributes->has($route_context_name)) {
          $value = $request->attributes->get($route_context_name);
        }
        else {
          $value = NULL;
        }
        $cacheability = new CacheableMetadata();
        $cacheability->setCacheContexts(['route']);

        $context = new Context(new ContextDefinition($route_context['type'], $context_name, FALSE), $value);
        $context->addCacheableDependency($cacheability);

        $contexts[$route_context_name] = $context;
      }
    }
    return $contexts;
  }

}
