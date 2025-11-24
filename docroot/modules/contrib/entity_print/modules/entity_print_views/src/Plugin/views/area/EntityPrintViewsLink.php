<?php

namespace Drupal\entity_print_views\Plugin\views\area;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\entity_print\Plugin\ExportTypeManagerInterface;
use Drupal\views\Plugin\views\area\AreaPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Views area handler for a Print button.
 *
 * @ingroup views_area_handlers
 *
 * @ViewsArea("entity_print_views_link")
 */
class EntityPrintViewsLink extends AreaPluginBase {

  /**
   * The export type manager.
   *
   * @var \Drupal\entity_print\Plugin\ExportTypeManagerInterface
   */
  protected $exportTypeManager;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * Constructs a new Entity instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\entity_print\Plugin\ExportTypeManagerInterface $export_type_manager
   *   The export type manager.
   * @param \Symfony\Component\HttpFoundation\Request $current_request
   *   The current request.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ExportTypeManagerInterface $export_type_manager, Request $current_request) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->exportTypeManager = $export_type_manager;
    $this->currentRequest = $current_request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.entity_print.export_type'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);
    $form['export_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Export Type'),
      '#options' => $this->exportTypeManager->getFormOptions(),
      '#required' => TRUE,
      '#default_value' => $this->options['export_type'],
    ];
    $form['link_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link text'),
      '#required' => TRUE,
      '#default_value' => $this->options['link_text'],
    ];
    $form['css_class'] = [
      '#title' => $this->t('CSS classes'),
      '#description' => $this->t('CSS classes to apply to the link. If using multiple classes, separate them by spaces.'),
      '#type' => 'textfield',
      '#default_value' => $this->options['css_class'],
    ];

    $displays = $this->view->displayHandlers->getConfiguration();
    $display_options = [];
    foreach ($displays as $display_id => $display_info) {
      $display_options[$display_id] = $display_info['display_title'];
    }
    $form['display_id'] = [
      '#type' => 'select',
      '#title' => $this->t('View Display'),
      '#options' => $display_options,
      '#required' => TRUE,
      '#default_value' => $this->options['display_id'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE): array {
    if ($empty && empty($this->options['empty'])) {
      return [];
    }

    $css_classes = [];
    // Process CSS classes to apply to print view link.
    if (!empty($this->options['css_class'])) {
      $user_css_classes = explode(' ', $this->options['css_class']);
      foreach ($user_css_classes as $css_class) {
        // The css class must include only letters, numbers, underscores and
        // dashes.
        if (preg_match('!^[A-Za-z0-9-_ ]+$!', $css_class)) {
          $css_classes[] = $css_class;
        }
      }
    }

    $route_params = [
      'export_type' => !empty($this->options['export_type']) ? $this->options['export_type'] : 'pdf',
      'view_name' => $this->view->storage->id(),
      'display_id' => $this->options['display_id'],
    ];

    $current_page = $this->currentRequest->query->get('page');
    $page_params = $current_page !== NULL ? ['page' => $current_page] : [];

    return [
      '#type' => 'link',
      '#title' => $this->options['link_text'],
      '#url' => Url::fromRoute('entity_print_views.view', $route_params, [
        'query' => $this->view->getExposedInput() + ['view_args' => $this->view->args] + $page_params,
        'attributes' => [
          'class' => $css_classes,
        ],
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['export_type'] = ['default' => 'pdf'];
    $options['link_text'] = ['default' => 'View PDF'];
    $options['css_class'] = ['default' => ''];
    $options['display_id'] = ['default' => $this->view->current_display];
    return $options;
  }

}
