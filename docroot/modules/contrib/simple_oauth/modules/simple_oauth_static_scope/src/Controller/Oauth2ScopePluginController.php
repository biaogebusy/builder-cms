<?php

namespace Drupal\simple_oauth_static_scope\Controller;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\simple_oauth_static_scope\Plugin\Oauth2ScopeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Defines a controller for the static scopes.
 */
class Oauth2ScopePluginController extends ControllerBase {

  /**
   * The scope plugin manager.
   *
   * @var \Drupal\simple_oauth_static_scope\Plugin\Oauth2ScopeManagerInterface
   */
  protected Oauth2ScopeManagerInterface $scopeManager;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * Oauth2ScopePluginController constructor.
   *
   * @param \Drupal\simple_oauth_static_scope\Plugin\Oauth2ScopeManagerInterface $oauth2_scope_manager
   *   The scope plugin manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(Oauth2ScopeManagerInterface $oauth2_scope_manager, RendererInterface $renderer) {
    $this->scopeManager = $oauth2_scope_manager;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('plugin.manager.oauth2_scope'),
      $container->get('renderer')
    );
  }

  /**
   * Provides the listing page for the static scopes (YAML).
   *
   * @return array
   *   A render array as expected by
   *   \Drupal\Core\Render\RendererInterface::render().
   */
  public function listing(): array {
    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        'name' => $this->t('Name'),
        'description' => $this->t('Description'),
        'operations' => $this->t('Operations'),
      ],
      '#title' => $this->t('Scopes'),
      '#rows' => [],
      '#empty' => $this->t('There are no scopes yet.'),
    ];

    foreach ($this->scopeManager->getDefinitions() as $plugin_definition) {
      $row['name'] = $plugin_definition['id'];
      $row['description'] = $plugin_definition['description'];
      $row['operations']['data'] = [
        '#type' => 'operations',
        '#links' => [
          'view' => [
            'title' => $this->t('View'),
            'url' => Url::fromRoute('plugin.oauth2_scope.view', ['plugin_id' => $plugin_definition['id']]),
          ],
        ],
      ];
      $build['table']['#rows'][$plugin_definition['id']] = $row;
    }

    return $build;
  }

  /**
   * Provides the view page for the static scope (YAML).
   *
   * @param string $plugin_id
   *   The OAuth2 scope plugin id.
   *
   * @return array
   *   A render array as expected by
   *   \Drupal\Core\Render\RendererInterface::render().
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function view(string $plugin_id): array {
    try {
      $plugin_definition = $this->scopeManager->getDefinition($plugin_id);
    }
    catch (PluginNotFoundException) {
      throw new HttpException(404, 'The scope does not exist.');
    }

    $build = [
      '#type' => 'table',
      '#header' => [
        'key' => $this->t('Key'),
        'value' => $this->t('Value'),
      ],
      '#rows' => [
        'id' => [
          'key' => 'id',
          'value' => $plugin_definition['id'],
        ],
        'description' => [
          'key' => 'description',
          'value' => $plugin_definition['description'],
        ],
        'grant_types' => [
          'key' => 'grant_types',
        ],
      ],
    ];

    foreach ($plugin_definition['grant_types'] as $grant_type_key => $grant_type) {
      $build['#rows'][$grant_type_key] = [
        'key' => [
          'data' => [
            '#prefix' => $this->getIndentation(),
            '#markup' => $grant_type_key,
          ],
        ],
      ];
      $build['#rows']["{$grant_type_key}_status"] = [
        'key' => [
          'data' => [
            '#prefix' => $this->getIndentation(2),
            '#markup' => 'status',
          ],
        ],
        'value' => $grant_type['status'] ? 'TRUE' : 'FALSE',
      ];
      if (!empty($grant_type['description'])) {
        $build['#rows']["{$grant_type_key}_description"] = [
          'key' => [
            'data' => [
              '#prefix' => $this->getIndentation(2),
              '#markup' => 'description',
            ],
          ],
          'value' => $grant_type['description'],
        ];
      }
    }

    $build['#rows']['umbrella'] = [
      'key' => 'umbrella',
      'value' => $plugin_definition['umbrella'] ? 'TRUE' : 'FALSE',
    ];
    foreach (['parent', 'granularity_id', 'permission', 'role'] as $key) {
      if (!isset($plugin_definition[$key])) {
        continue;
      }
      $build['#rows'][$key] = [
        'key' => $key,
        'value' => $plugin_definition[$key],
      ];
    }

    return $build;
  }

  /**
   * Get the rendered indentation.
   *
   * @param int $depth
   *   The depth to indent.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   Returns the rendered indentation.
   *
   * @throws \Exception
   */
  protected function getIndentation(int $depth = 1): MarkupInterface {
    $indentation = [
      '#theme' => 'indentation',
      '#size' => $depth,
    ];

    return $this->renderer->render($indentation);
  }

}
