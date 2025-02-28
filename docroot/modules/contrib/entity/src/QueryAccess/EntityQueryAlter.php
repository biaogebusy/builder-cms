<?php

namespace Drupal\entity\QueryAccess;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\Sql\Tables;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines a class for altering entity queries.
 *
 * EntityQuery doesn't have an alter hook, forcing this class to operate
 * on the underlying SQL query, duplicating the EntityQuery condition logic.
 *
 * @internal
 */
class EntityQueryAlter implements ContainerInjectionInterface {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new EntityQueryAlter object.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer, RequestStack $request_stack) {
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('request_stack')
    );
  }

  /**
   * Alters the select query for the given entity type.
   *
   * @param \Drupal\Core\Database\Query\SelectInterface $query
   *   The select query.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   */
  public function alter(SelectInterface $query, EntityTypeInterface $entity_type) {
    if (!$entity_type->hasHandlerClass('query_access')) {
      return;
    }
    $entity_type_id = $entity_type->id();
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    if (!$storage instanceof SqlContentEntityStorage) {
      return;
    }

    /** @var \Drupal\entity\QueryAccess\QueryAccessHandlerInterface $query_access */
    $query_access = $this->entityTypeManager->getHandler($entity_type_id, 'query_access');
    $conditions = $query_access->getConditions($query->getMetaData('op') ?: 'view');
    if ($conditions->isAlwaysFalse()) {
      $query->where('1 = 0');
    }
    elseif (count($conditions)) {
      $sql_conditions = $this->mapConditions($conditions, $query);
      $query->condition($sql_conditions);
    }

    $this->applyCacheability(CacheableMetadata::createFromObject($conditions));
  }

  /**
   * Maps an entity type's access conditions to SQL conditions.
   *
   * @param \Drupal\entity\QueryAccess\ConditionGroup $conditions
   *   The access conditions.
   * @param \Drupal\Core\Database\Query\SelectInterface $query
   *   The SQL query.
   * @param bool $nested_inside_or
   *   Whether the access conditions are nested inside an OR condition.
   *
   * @return \Drupal\Core\Database\Query\ConditionInterface
   *   The SQL conditions.
   */
  protected function mapConditions(ConditionGroup $conditions, SelectInterface $query, $nested_inside_or = FALSE) {
    $sql_condition = $query->conditionGroupFactory($conditions->getConjunction());
    $tables = new Tables($query);
    $nested_inside_or = $nested_inside_or || $conditions->getConjunction() == 'OR';
    foreach ($conditions->getConditions() as $condition) {
      if ($condition instanceof ConditionGroup) {
        $nested_sql_conditions = $this->mapConditions($condition, $query, $nested_inside_or);
        $sql_condition->condition($nested_sql_conditions);
      }
      else {
        // Access conditions don't specify a langcode.
        $langcode = NULL;
        $type = $nested_inside_or || $condition->getOperator() === 'IS NULL' ? 'LEFT' : 'INNER';
        $sql_field = $tables->addField($condition->getField(), $type, $langcode);
        $value = $condition->getValue();
        $operator = $condition->getOperator();
        // Using LIKE/NOT LIKE ensures a case insensitive comparison.
        // @see \Drupal\Core\Entity\Query\Sql\Condition::translateCondition().
        $case_sensitive = $tables->isFieldCaseSensitive($condition->getField());
        $operator_map = [
          '=' => 'LIKE',
          '<>' => 'NOT LIKE',
        ];
        if ($case_sensitive === FALSE && isset($operator_map[$operator])) {
          $operator = $operator_map[$operator];
          $value = $query->escapeLike($value);
        }

        $sql_condition->condition($sql_field, $value, $operator);
      }
    }

    return $sql_condition;
  }

  /**
   * Applies the cacheablity metadata to the current request.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheable_metadata
   *   The cacheability metadata.
   */
  protected function applyCacheability(CacheableMetadata $cacheable_metadata) {
    $request = $this->requestStack->getCurrentRequest();
    if ($request->isMethodCacheable() && $this->renderer->hasRenderContext() && $this->hasCacheableMetadata($cacheable_metadata)) {
      $build = [];
      $cacheable_metadata->applyTo($build);
      $this->renderer->render($build);
    }
  }

  /**
   * Check if the cacheable metadata is not empty.
   *
   * An empty cacheable metadata object has no context, tags, and is permanent.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheable_metadata
   *   The cacheable metadata.
   *
   * @return bool
   *   TRUE if there is cacheability metadata, otherwise FALSE.
   */
  protected function hasCacheableMetadata(CacheableMetadata $cacheable_metadata) {
    return $cacheable_metadata->getCacheMaxAge() !== Cache::PERMANENT
      || count($cacheable_metadata->getCacheContexts()) > 0
      || count($cacheable_metadata->getCacheTags()) > 0;
  }

}
