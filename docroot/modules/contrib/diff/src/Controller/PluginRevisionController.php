<?php

declare(strict_types=1);

namespace Drupal\diff\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\diff\DiffEntityComparison;
use Drupal\diff\DiffLayoutManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Base class for controllers that return responses on entity revision routes.
 */
class PluginRevisionController extends ControllerBase {

  protected ImmutableConfig $config;

  /**
   * Constructs a PluginRevisionController object.
   */
  public function __construct(
    protected DiffEntityComparison $entityComparison,
    protected DiffLayoutManager $diffLayoutManager,
    protected RequestStack $requestStack,
  ) {
    /** @var \Drupal\Core\Config\ImmutableConfig $config */
    $config = $this->config('diff.settings');
    $this->config = $config;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('diff.entity_comparison'),
      $container->get('plugin.manager.diff.layout'),
      $container->get('request_stack'),
    );
  }

  /**
   * Get all the revision ids of given entity id.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage manager.
   * @param int|string $entity_id
   *   The entity to find revisions of.
   *
   * @return int[]
   *   The revision ids.
   */
  public function getRevisionIds(EntityStorageInterface $storage, int|string $entity_id): array {
    $result = $storage->getQuery()
      ->allRevisions()
      ->condition($storage->getEntityType()->getKey('id'), $entity_id)
      ->accessCheck(FALSE)
      ->execute();
    $result_array = \array_keys($result);
    \sort($result_array);
    return $result_array;
  }

  /**
   * Returns a table which shows the differences between two entity revisions.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Entity\ContentEntityInterface $left_revision
   *   The left revision.
   * @param \Drupal\Core\Entity\ContentEntityInterface $right_revision
   *   The right revision.
   * @param string $filter
   *   If $filter == 'raw' raw text is compared (including html tags)
   *   If filter == 'raw-plain' markdown function is applied to the text before
   *   comparison.
   *
   * @return array
   *   Table showing the diff between the two entity revisions.
   */
  public function compareEntityRevisions(RouteMatchInterface $route_match, ContentEntityInterface $left_revision, ContentEntityInterface $right_revision, $filter): array {
    $entity_type_id = $left_revision->getEntityTypeId();
    $entity = $route_match->getParameter($entity_type_id);
    if (!$entity instanceof ContentEntityInterface) {
      return [];
    }
    if ($entity->id() !== $left_revision->id() || $entity->id() !== $right_revision->id()) {
      throw new NotFoundHttpException();
    }
    if (!$right_revision->access('view') || !$left_revision->access('view')) {
      throw new AccessDeniedHttpException();
    }

    $entity_type_id = $entity->getEntityTypeId();
    $storage = $this->entityTypeManager()->getStorage($entity_type_id);
    if (!$storage instanceof RevisionableStorageInterface) {
      return [];
    }
    // Get language from the entity context.
    $langcode = $entity->language()->getId();

    // Get left and right revision in current language.
    $left_revision = $left_revision->getTranslation($langcode);
    $right_revision = $right_revision->getTranslation($langcode);

    $revisions_ids = [];
    // Filter revisions of current translation and where the translation is
    // affected.
    foreach ($this->getRevisionIds($storage, $entity->id()) as $revision_id) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $revision */
      $revision = $storage->loadRevision($revision_id);
      if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
        $revisions_ids[] = $revision_id;
      }
    }

    $build = [
      '#title' => $this->t('Changes to %title', ['%title' => $entity->label()]),
      'header' => [
        '#prefix' => '<header class="diff-header">',
        '#suffix' => '</header>',
      ],
      'controls' => [
        '#prefix' => '<div class="diff-controls">',
        '#suffix' => '</div>',
      ],
    ];

    // Build the navigation links.
    $build['header']['diff_navigation'] = $this->buildRevisionsNavigation($entity, $revisions_ids, (int) $left_revision->getRevisionId(), (int) $right_revision->getRevisionId(), $filter);

    // Build the layout filter.
    $build['controls']['diff_layout'] = [
      '#type' => 'item',
      '#title' => $this->t('Layout'),
      '#wrapper_attributes' => ['class' => 'diff-controls__item'],
      'filter' => $this->buildLayoutNavigation($entity, $left_revision->getRevisionId(), $right_revision->getRevisionId(), $filter),
    ];

    // Build the diff comparison with the plugin.
    if ($plugin = $this->diffLayoutManager->createInstance($filter)) {
      $build = \array_merge_recursive($build, $plugin->build($left_revision, $right_revision, $entity));
      $build['diff']['#prefix'] = '<div class="diff-responsive-table-wrapper">';
      $build['diff']['#suffix'] = '</div>';
      $build['diff']['#attributes']['class'][] = 'diff-responsive-table';
    }

    $build['#attached']['library'][] = 'diff/diff.general';
    return $build;
  }

  /**
   * Builds a navigation dropdown button between the layout plugins.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to be compared.
   * @param int|string $left_revision_id
   *   Revision id of the left revision.
   * @param int|string $right_revision_id
   *   Revision id of the right revision.
   * @param string $active_filter
   *   The active filter.
   *
   * @return array
   *   The layout filter.
   */
  protected function buildLayoutNavigation(ContentEntityInterface $entity, int|string $left_revision_id, int|string $right_revision_id, string $active_filter): array {
    $links = [];
    $layouts = $this->diffLayoutManager->getPluginOptions();
    foreach ($layouts as $key => $value) {
      $links[$key] = [
        'title' => $value,
        'url' => static::diffRoute($entity, $left_revision_id, $right_revision_id, $key),
      ];
    }

    // Set as the first element the current filter.
    $filter = $links[$active_filter];
    unset($links[$active_filter]);
    \array_unshift($links, $filter);

    return [
      '#type' => 'operations',
      '#links' => $links,
    ];
  }

  /**
   * Creates navigation links between the previous changes and the new ones.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to be compared.
   * @param array $revision_ids
   *   The revision ids.
   * @param int $left_revision_id
   *   Revision id of the left revision.
   * @param int $right_revision_id
   *   Revision id of the right revision.
   * @param string $filter
   *   The filter.
   *
   * @return array
   *   The revision navigation links.
   */
  protected function buildRevisionsNavigation(ContentEntityInterface $entity, array $revision_ids, int $left_revision_id, int $right_revision_id, string $filter): array {
    $revisions_count = \count($revision_ids);
    $layout_options = &\drupal_static(__FUNCTION__);
    if (!isset($layout_options)) {
      $layout_options = UrlHelper::filterQueryParameters($this->requestStack->getCurrentRequest()->query->all(), ['page']);
    }
    // If there are only 2 revision return an empty row.
    if ($revisions_count === 2) {
      return [];
    }

    $left_link = $right_link = '';
    $element = [
      '#type' => 'item',
      '#title' => $this->t('Navigation'),
      '#wrapper_attributes' => ['class' => 'diff-navigation'],
    ];
    $i = 0;
    // Find the previous revision.
    while (\array_key_exists($i, $revision_ids) && $left_revision_id > $revision_ids[$i]) {
      ++$i;
    }
    if ($i !== 0) {
      // Build the left link.
      $left_link = Link::fromTextAndUrl($this->t('Previous change'), static::diffRoute($entity, $revision_ids[$i - 1], $left_revision_id, $filter, $layout_options))->toString();
    }
    $element['left'] = [
      '#type' => 'markup',
      '#markup' => $left_link,
      '#prefix' => '<div class="diff-navigation__link prev-link">',
      '#suffix' => '</div>',
    ];
    // Find the next revision.
    $i = 0;
    while (\array_key_exists($i, $revision_ids) && $right_revision_id >= $revision_ids[$i]) {
      ++$i;
    }
    if ($revisions_count !== $i && $revision_ids[$i - 1] !== $revision_ids[$revisions_count - 1]) {
      // Build the right link.
      $right_link = Link::fromTextAndUrl($this->t('Next change'), static::diffRoute($entity, $right_revision_id, $revision_ids[$i], $filter, $layout_options))->toString();
    }
    $element['right'] = [
      '#type' => 'markup',
      '#markup' => $right_link,
      '#prefix' => '<div class="diff-navigation__link next-link">',
      '#suffix' => '</div>',
    ];
    return $element;
  }

  /**
   * Creates an url object for diff.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to be compared.
   * @param int|string $left_revision_id
   *   Revision id of the left revision.
   * @param int|string $right_revision_id
   *   Revision id of the right revision.
   * @param string|null $layout
   *   (optional) The filter/layout added to the route.
   * @param array|null $layout_options
   *   (optional) The layout options provided by the selected layout.
   *
   * @return \Drupal\Core\Url
   *   The URL object.
   */
  public static function diffRoute(ContentEntityInterface $entity, int|string $left_revision_id, int|string $right_revision_id, ?string $layout = NULL, ?array $layout_options = NULL): Url {
    $entity_type_id = $entity->getEntityTypeId();
    // @todo Remove the diff.revisions_diff route so we avoid adding extra cases.
    if ($entity->getEntityTypeId() === 'node') {
      $route_name = 'diff.revisions_diff';
    }
    else {
      $route_name = "entity.$entity_type_id.revisions_diff";
    }
    $route_parameters = [
      $entity_type_id => $entity->id(),
      'left_revision' => $left_revision_id,
      'right_revision' => $right_revision_id,
    ];
    if ($layout !== NULL) {
      $route_parameters['filter'] = $layout;
    }
    $options = [];
    if ($layout_options !== NULL) {
      $options = [
        'query' => $layout_options,
      ];
    }
    return Url::fromRoute($route_name, $route_parameters, $options);
  }

}
