<?php

declare(strict_types=1);

namespace Drupal\charts\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Utility\Token;
use Drupal\views\ViewExecutable;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service class for implementing module hooks for Charts.
 */
class ChartsHooks {

  use StringTranslationTrait;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected readonly Token $token;

  /**
   * Constructs a new ChartsHooks object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\Core\Extension\ExtensionPathResolver $extensionPathResolver
   *   The extension path resolver.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Utility\Token|null $token
   *   The token service (optional for backward compatibility).
   */
  public function __construct(
    protected readonly RequestStack $requestStack,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly ExtensionPathResolver $extensionPathResolver,
    protected readonly ModuleHandlerInterface $moduleHandler,
    ?Token $token = NULL,
  ) {
    if ($token === NULL) {
      // @phpstan-ignore-next-line
      $this->token = \Drupal::service('token');
    }
    else {
      $this->token = $token;
    }
  }

  /**
   * Implements hook_theme().
   *
   * @return array
   *   The theme array.
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'charts_chart' => [
        'render element' => 'element',
      ],
    ];
  }

  /**
   * Implements hook_views_data().
   *
   * @return array
   *   The views data.
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('views_data')]
  public function viewsData(): array {
    $data['charts_fields']['table']['group'] = $this->t('Charts');
    $data['charts_fields']['table']['join'] = [
      '#global' => [],
    ];
    $data['charts_fields']['field_charts_fields_scatter'] = [
      'title' => $this->t('Scatter Field'),
      'help' => $this->t('Use this field for your data field in a scatter plot.'),
      'field' => ['id' => 'field_charts_fields_scatter'],
    ];
    $data['charts_fields']['field_charts_fields_bubble'] = [
      'title' => $this->t('Bubble Field'),
      'help' => $this->t('Use this field for your data field in a bubble chart.'),
      'field' => ['id' => 'field_charts_fields_bubble'],
    ];
    $data['charts_fields']['field_charts_numeric_array'] = [
      'title' => $this->t('Numeric Array'),
      'help' => $this->t('Use this field for your data field in a chart of 1-10 array items.'),
      'field' => ['id' => 'field_charts_numeric_array'],
    ];
    $data['charts_fields']['field_exposed_chart_type'] = [
      'title' => $this->t('Exposed Chart Type'),
      'help' => $this->t('Use this field for exposing chart type.'),
      'field' => ['id' => 'field_exposed_chart_type'],
    ];

    return $data;
  }

  /**
   * Implements hook_views_pre_view().
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The views executable.
   * @param string $display_id
   *   The views display ID.
   * @param array $args
   *   The views arguments.
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('views_pre_view')]
  public function viewsPreView(ViewExecutable $view, string $display_id, array &$args): void {
    if (array_key_exists('fields', $view->display_handler->options)) {
      $fields = $view->display_handler->getOption('fields');
      $hasViewsFieldsOnOffHandler = FALSE;
      foreach ($fields as $field) {
        if (($field['plugin_id'] ?? '') === 'field_exposed_chart_type') {
          $hasViewsFieldsOnOffHandler = TRUE;
          break;
        }
      }
      if ($hasViewsFieldsOnOffHandler) {
        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
          $params = array_merge($request->query->all(), $request->request->all());
          foreach ($params as $key => $value) {
            if (str_starts_with($key, 'ct')) {
              $view->storage->set('exposed_chart_type', $value);
            }
          }
          $view->element['#cache']['contexts'][] = 'url';
        }
      }
    }
  }

  /**
   * Implements hook_library_info_alter().
   *
   * @param array $libraries
   *   The library definitions.
   * @param string $extension
   *   The enabled modules.
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('library_info_alter')]
  public function libraryInfoAlter(array &$libraries, string $extension): void {
    if (!str_starts_with($extension, 'charts_')) {
      return;
    }

    $config = $this->configFactory->get('charts.settings');
    if (!$config->get('advanced.requirements.cdn')) {
      return;
    }

    foreach ($libraries as &$library) {
      if (isset($library['cdn']) && is_array($library['cdn'])) {
        $this->alterRecursive($library, $library['cdn']);
      }
    }
  }

  /**
   * Implements hook_preprocess_HOOK().
   *
   * @param array $variables
   *   The variables array.
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('preprocess_charts_chart', module: 'template')]
  public function templatePreprocessChartsChart(&$variables): void {
    $element = $variables['element'];
    $chart_attributes = $element['#attributes'] ?? [];
    $chart_id = $element['#id'];
    $chart_attributes['id'] = $chart_id;
    $chart_attributes['class'][] = 'chart';
    $chart_attributes['class'][] = 'chart-visual';
    $chart_attributes['aria-hidden'] = 'true';

    // Wrapper attributes for layout/targeting.
    $wrapper_attributes = new Attribute();
    $wrapper_attributes->addClass('charts-wrapper');
    $variables['attributes'] = $wrapper_attributes;

    // Retrieve raw values.
    $variables['title'] = $element['#title'] ?? '';
    $figure_caption = $element['#figure_caption'] ?? '';
    $chart_summary = $element['#chart_summary'] ?? '';

    // Token replacement.
    if (!empty($figure_caption) || !empty($chart_summary)) {
      $token_data = [];
      $token_options = [
        'clear' => TRUE,
      ];
      if (!empty($element['#entity']) && $element['#entity'] instanceof EntityInterface) {
        $token_data[$element['#entity']->getEntityTypeId()] = $element['#entity'];
      }
      if ($figure_caption) {
        $figure_caption = $this->token->replace((string) $figure_caption, $token_data, $token_options);
      }
      if ($chart_summary) {
        $chart_summary = $this->token->replace((string) $chart_summary, $token_data, $token_options);
      }
    }

    // Initialize variables to store processed text.
    $processed_caption = '';
    $processed_summary = '';

    // Process figure caption.
    if ($figure_caption) {
      $decoded_caption = Html::decodeEntities((string) $figure_caption);
      $safe_caption = Xss::filterAdmin($decoded_caption);
      $processed_caption = Markup::create($safe_caption);
    }

    // Process chart summary (render as plain text).
    if ($chart_summary) {
      $decoded_summary = Html::decodeEntities((string) $chart_summary);
      $processed_summary = strip_tags($decoded_summary);
    }

    // Accessible table data.
    $table_alternative = $element['#table_alternative'] ?? [];
    $table_visibility = $element['#table_visibility'] ?? 'disabled';

    // Set up the figure wrapper attributes.
    $figure_attributes = new Attribute([
      'role' => 'figure',
      'aria-label' => $variables['title'] ?: $this->t('Chart'),
      'class' => [
        'charts-figure',
      ],
      'tabindex' => '0',
    ]);

    if ($processed_summary) {
      $figure_attributes->setAttribute('aria-describedby', $chart_id . '-summary');
    }

    $variables['content'] = [
      '#prefix' => '<div ' . $figure_attributes . '>',
      '#suffix' => '</div>',
    ];

    // Add the visual chart container.
    $variables['content']['chart'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => $chart_attributes,
      '#value' => $element['#chart'] ?? '',
    ];

    // Add the screen reader summary (visually hidden).
    if ($processed_summary) {
      $variables['content']['summary'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'id' => $chart_id . '-summary',
          'class' => [
            'visually-hidden',
          ],
        ],
        '#value' => $processed_summary,
      ];
    }

    // Add the visible caption.
    if ($processed_caption) {
      $variables['content']['caption'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'id' => $chart_id . '-caption',
          'class' => [
            'charts-figure-caption',
          ],
        ],
        '#value' => $processed_caption,
      ];
    }

    // Add the accessible table alternative.
    if (!empty($table_alternative) && $table_visibility !== 'disabled') {
      $variables['content']['table'] = [
        '#prefix' => '<div class="charts-accessible-table-wrapper">',
        '#suffix' => '</div>',
        'table' => $table_alternative,
      ];
    }

    $variables['content_prefix'] = $element['#content_prefix'] ?? [];
    $variables['content_suffix'] = $element['#content_suffix'] ?? [];

    // Chart Debug output.
    $config = $this->configFactory->get('charts.settings');
    $variables['debug'] = [];
    if (!empty($config->get('advanced.debug'))) {
      $raw_json = $element['#attributes']['data-chart'] ?? '';
      $pretty_json = $raw_json;
      if ($raw_json) {
        $decoded = json_decode($raw_json);
        $pretty_json = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
      }
      $variables['debug'] = [
        '#type' => 'details',
        '#title' => $this->t('Chart JSON'),
        '#open' => FALSE,
        '#attributes' => [
          'data-charts-debug-container' => TRUE,
        ],
        '#collapsible' => TRUE,
        'json' => [
          '#prefix' => '<pre class="language-json"><code class="language-json">',
          '#plain_text' => $pretty_json,
          '#suffix' => '</code></pre>',
        ],
      ];
    }
  }

  /**
   * Recursive helper for library alter.
   *
   * @param array $library
   *   The library.
   * @param array $cdn
   *   The CDN info.
   */
  private function alterRecursive(array &$library, array $cdn): void {
    foreach ($library as $key => &$value) {
      if (!is_string($key) || !is_array($value) || $key === 'cdn') {
        continue;
      }

      foreach ($cdn as $source => $destination) {
        if ($this->checkSourceExists($source)) {
          continue;
        }
        if (str_starts_with($key, $source)) {
          $uri = str_replace($source, $destination, $key);
          $library[$uri] = $value;
          $library[$uri]['type'] = 'external';
          unset($library[$key]);
          break;
        }
      }

      $this->alterRecursive($value, $cdn);
    }
  }

  /**
   * Checks whether a library source directory or file exists locally.
   *
   * @param string $source
   *   The source directory or file.
   *
   * @return bool
   *   True when the file exist, FALSE otherwise.
   */
  private function checkSourceExists(string $source): bool {
    $search_paths = [DRUPAL_ROOT];

    // Find the profile via the module list.
    $install_profile = NULL;
    foreach ($this->moduleHandler->getModuleList() as $module) {
      if ($module->getType() === 'profile') {
        $install_profile = $module->getName();
        break;
      }
    }

    if ($install_profile) {
      $search_paths[] = $this->extensionPathResolver->getPath('profile', $install_profile);
    }

    foreach ($search_paths as $path) {
      if (file_exists($path . '/' . ltrim($source, '/'))) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
