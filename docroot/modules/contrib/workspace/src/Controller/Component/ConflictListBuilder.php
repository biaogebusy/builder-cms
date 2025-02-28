<?php

namespace Drupal\workspace\Controller\Component;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\multiversion\Entity\Index\RevisionIndexInterface;
use Drupal\multiversion\Entity\Workspace;
use Drupal\multiversion\Workspace\ConflictTrackerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A list builder for entity revision conflicts.
 *
 * Note: this class does not implement EntityListBuilderInterface because we
 * don't need the getStorage. We aren't showing just one entity type here, so we
 * need the storage for each revision.
 */
class ConflictListBuilder {
  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * @var \Drupal\multiversion\Workspace\ConflictTrackerInterface
   */
  protected $conflictTracker;

  /**
   * @var \Drupal\multiversion\Entity\Index\RevisionIndexInterface
   */
  protected $revisionIndex;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Inject services needed to build the list.
   *
   * @param \Drupal\multiversion\Workspace\ConflictTrackerInterface $conflict_tracker
   *   The conflict tracking service.
   * @param \Drupal\multiversion\Entity\Index\RevisionIndexInterface $revision_index
   *   The entity index service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   A date formatter to show pretty dates.
   */
  public function __construct(
    ConflictTrackerInterface $conflict_tracker,
    RevisionIndexInterface $revision_index,
    EntityTypeManagerInterface $entity_type_manager,
    DateFormatterInterface $date_formatter
  ) {
    $this->conflictTracker = $conflict_tracker;
    $this->revisionIndex = $revision_index;
    $this->entityTypeManager = $entity_type_manager;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * Instantiates a new instance of this list builder.
   *
   * Because we don't have a single entity type, we cannot use
   * EntityHandlerInterface::createInstance.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container this object should use.
   *
   * @return static
   *   A new instance of this list builder.
   */
  public static function createInstance(ContainerInterface $container) {
    return new self(
      $container->get('workspace.conflict_tracker'),
      $container->get('multiversion.entity_index.rev'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter')
    );
  }

  /**
   * Build the table header.
   *
   * @return array
   *   The header array used by table render arrays.
   */
  public function buildHeader() {
    $header = [
      'title' => $this->t('Title'),
      'type' => [
        'data' => $this->t('Content type'),
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
      'author' => [
        'data' => $this->t('Author'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'status' => $this->t('Status'),
      'changed' => [
        'data' => $this->t('Updated'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
    ];
    return $header;
  }

  /**
   * Build a row for the given entity.
   *
   * @todo Handle translations.
   *
   * @see \Drupal\node\NodeListBuilder::buildRow
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to build the render array row for.
   *
   * @return array
   *   A row render array used by a table render array.
   */
  public function buildRow(EntityInterface $entity) {
    $entity_type = $entity->getEntityType();

    $row['title'] = $entity->label();

    // @todo Is there a way to get the human readable name for the bundle?
    $row['type'] = $entity->bundle();

    $uid_key = $entity_type->getKey('uid');
    if ($uid_key) {
      $row['author']['data'] = [
        '#theme' => 'username',
        '#account' => $entity->get($uid_key)->entity,
      ];
    }
    else {
      $row['author'] = $this->t('None');
    }

    $status_key = $entity_type->getKey('status');
    if ($status_key) {
      $row['status'] = $entity->get($status_key)->value ? $this->t('published') : $this->t('not published');
    }
    else {
      $row['status'] = $this->t('published');
    }

    // @todo Is there an entity key for changed time?
    if (method_exists($entity, 'getChangedTime')) {
      $row['changed'] = $this->dateFormatter->format($entity->getChangedTime(), 'short');
    }

    return $row;
  }

  /**
   * Get the title of the entire table.
   *
   * @return string
   *   The title to use for the whole table.
   */
  public function getTitle() {
    return '';
  }

  /**
   * Load the entities needed for the table.
   *
   * @see workspace_preprocess_workspace_rev
   *
   * @param string $workspace_id
   *   The workspace ID to build the conflict list for.
   *
   * @return \Drupal\Core\Entity\EntityInterface[] An array of entities.
   * An array of entities.
   */
  public function load($workspace_id) {
    /** \Drupal\multiversion\Entity\Workspace $workspace */
    $workspace = Workspace::load($workspace_id);

    $conflicts = $this->conflictTracker
      ->useWorkspace($workspace)
      ->getAll();

    $entity_revisions = [];
    foreach ($conflicts as $uuid => $conflict) {
      // @todo figure out why this is an array and what to do if there is more than 1
      // @todo what happens when the conflict value is not "available"? what does this mean?
      $conflict_keys = array_keys($conflict);
      $rev = reset($conflict_keys);
      $rev_info = $this->revisionIndex
        ->useWorkspace($workspace_id)
        ->get("$uuid:$rev");

      if (!empty($rev_info['revision_id'])) {
        $entity_revisions[] = $this->entityTypeManager
          ->getStorage($rev_info['entity_type_id'])
          ->useWorkspace($workspace_id)
          ->loadRevision($rev_info['revision_id']);
      }
    }

    return $entity_revisions;
  }

  /**
   * Build the render array to display on the page.
   *
   * @param string $workspace_id
   *   The workspace ID to build the conflict list for.
   *
   * @return array
   *   A table render array to show on the page.
   */
  public function buildList($workspace_id) {
    $build['table'] = [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#title' => $this->getTitle(),
      '#rows' => [],
      '#empty' => 'There are no conflicts.',
    ];

    $entities = $this->load($workspace_id);
    foreach ($entities as $entity) {
      if ($row = $this->buildRow($entity)) {
        $build['table']['#rows'][] = $row;
      }
    }

    // Only add the pager if a limit is specified.
    if (!empty($this->limit)) {
      $build['pager'] = [
        '#type' => 'pager',
      ];
    }

    return $build;
  }

}
