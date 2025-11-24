<?php

declare(strict_types=1);

namespace Drupal\diff\Plugin\diff\Layout;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\PhpStorage\PhpStorageFactory;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\diff\Attribute\DiffLayoutBuilder;
use Drupal\diff\Controller\PluginRevisionController;
use Drupal\diff\DiffEntityComparison;
use Drupal\diff\DiffEntityParser;
use Drupal\diff\DiffLayoutBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides Visual Inline diff layout.
 */
#[DiffLayoutBuilder(
  id: 'visual_inline',
  label: new TranslatableMarkup('Visual Inline'),
  description: new TranslatableMarkup('Visual layout, displays revision comparison using the entity type view mode.'),
)]
class VisualInlineDiffLayout extends DiffLayoutBase {

  /**
   * The html diff service.
   *
   * @var \HtmlDiffAdvancedInterface
   */
  protected $htmlDiff;

  /**
   * Constructs a VisualInlineDiffLayout object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config,
    EntityTypeManagerInterface $entity_type_manager,
    DiffEntityParser $entity_parser,
    DateFormatterInterface $date,
    protected RendererInterface $renderer,
    protected DiffEntityComparison $entityComparison,
    \HtmlDiffAdvancedInterface $html_diff,
    protected RequestStack $requestStack,
    protected EntityDisplayRepositoryInterface $entityDisplayRepository,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $config, $entity_type_manager, $entity_parser, $date);
    $storage = PhpStorageFactory::get('html_purifier_serializer');
    if (!$storage->exists('cache.php')) {
      $storage->save('cache.php', 'dummy');
    }
    $html_diff->getConfig()->setPurifierCacheLocation(\dirname($storage->getFullPath('cache.php')));
    $this->htmlDiff = $html_diff;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('diff.entity_parser'),
      $container->get('date.formatter'),
      $container->get('renderer'),
      $container->get('diff.entity_comparison'),
      $container->get('diff.html_diff'),
      $container->get('request_stack'),
      $container->get('entity_display.repository'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(ContentEntityInterface $left_revision, ContentEntityInterface $right_revision, ContentEntityInterface $entity): array {
    // Build the revisions data.
    $build = $this->buildRevisionsData($left_revision, $right_revision);

    $this->entityTypeManager->getStorage($entity->getEntityTypeId())->resetCache([$entity->id()]);
    // Build the view modes filter.
    $options = [];
    // Get all view modes for entity type.
    $view_modes = $this->entityDisplayRepository->getViewModeOptionsByBundle($entity->getEntityTypeId(), $entity->bundle());
    foreach ($view_modes as $view_mode => $view_mode_info) {
      // Skip view modes that are not used in the front end.
      if (\in_array($view_mode, ['rss', 'search_index'])) {
        continue;
      }
      $options[$view_mode] = [
        'title' => $view_mode_info,
        'url' => PluginRevisionController::diffRoute($entity,
          $left_revision->getRevisionId(),
          $right_revision->getRevisionId(),
          'visual_inline',
          ['view_mode' => $view_mode],
        ),
      ];
    }

    $default_view_mode = $this->configFactory->get('diff.settings')->get('general_settings.visual_default_view_mode');
    // If the configured default view mode is not enabled on the current
    // bundle type, fallback to one of the enabled ones.
    if (!\is_string($default_view_mode) || !\in_array($default_view_mode, \array_keys($view_modes), TRUE)) {
      $keys = \array_keys($options);
      $active_option = \reset($keys);
    }
    else {
      $active_option = $default_view_mode;
    }
    $active_view_mode = $this->requestStack->getCurrentRequest()->query->get('view_mode') ?: $active_option;

    $filter = $options[$active_view_mode];
    unset($options[$active_view_mode]);
    \array_unshift($options, $filter);

    $build['controls']['view_mode'] = [
      '#type' => 'item',
      '#title' => $this->t('View mode'),
      '#wrapper_attributes' => ['class' => 'diff-controls__item'],
      'filter' => [
        '#type' => 'operations',
        '#links' => $options,
      ],
    ];

    $view_builder = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId());
    // Trigger exclusion of interactive items like on preview.
    $left_revision->in_preview = TRUE;
    $right_revision->in_preview = TRUE;
    $left_view = $view_builder->view($left_revision, $active_view_mode);
    $right_view = $view_builder->view($right_revision, $active_view_mode);

    // Avoid render cache from being built.
    unset($left_view['#cache']);
    unset($right_view['#cache']);

    $html_1 = $this->renderer->render($left_view);
    $html_2 = $this->renderer->render($right_view);

    $this->htmlDiff->setOldHtml($html_1);
    $this->htmlDiff->setNewHtml($html_2);
    $this->htmlDiff->build();

    $build['diff'] = [
      '#markup' => $this->htmlDiff->getDifference(),
      '#weight' => 10,
    ];

    $build['#attached']['library'][] = 'diff/diff.visual_inline';
    return $build;
  }

}
