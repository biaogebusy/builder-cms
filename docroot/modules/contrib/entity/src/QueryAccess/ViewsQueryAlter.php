<?php

namespace Drupal\entity\QueryAccess;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\DefaultTableMapping;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Render\RendererInterface;
use Drupal\views\Plugin\views\query\Sql;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines a class for altering views queries.
 *
 * @internal
 */
class ViewsQueryAlter implements ContainerInjectionInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

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
   * Constructs a new ViewsQueryAlter object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(Connection $connection, EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer, RequestStack $request_stack) {
    $this->connection = $connection;
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
      $container->get('database'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('request_stack')
    );
  }

  /**
   * Alters the given views query.
   *
   * @param \Drupal\views\Plugin\views\query\Sql $query
   *   The views query.
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   */
  public function alter(Sql $query, ViewExecutable $view) {
    $table_info = $query->getEntityTableInfo();
    $base_table = reset($table_info);
    if (empty($base_table['entity_type']) || $base_table['relationship_id'] != 'none') {
      return;
    }
    $entity_type_id = $base_table['entity_type'];
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    if (!$entity_type->hasHandlerClass('query_access')) {
      return;
    }
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    if (!$storage instanceof SqlContentEntityStorage) {
      return;
    }

    /** @var \Drupal\entity\QueryAccess\QueryAccessHandlerInterface $query_access */
    $query_access = $this->entityTypeManager->getHandler($entity_type_id, 'query_access');
    $conditions = $query_access->getConditions('view');
    if ($conditions->isAlwaysFalse()) {
      $query->addWhereExpression(0, '1 = 0');
    }
    elseif (count($conditions)) {
      // Store the data table, in case mapConditions() needs to join it in.
      $base_table['data_table'] = $entity_type->getDataTable();
      $field_storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
      /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $table_mapping */
      $table_mapping = $storage->getTableMapping();
      $sql_conditions = $this->mapConditions($conditions, $query, $base_table, $field_storage_definitions, $table_mapping);
      $query->addWhere(0, $sql_conditions);
    }

    $this->applyCacheability(CacheableMetadata::createFromObject($conditions));
  }

  /**
   * Maps an entity type's access conditions to views SQL conditions.
   *
   * @param \Drupal\entity\QueryAccess\ConditionGroup $conditions
   *   The access conditions.
   * @param \Drupal\views\Plugin\views\query\Sql $query
   *   The views query.
   * @param array $base_table
   *   The base table information.
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface[] $field_storage_definitions
   *   The field storage definitions.
   * @param \Drupal\Core\Entity\Sql\DefaultTableMapping $table_mapping
   *   The table mapping.
   *
   * @return \Drupal\Core\Database\Query\ConditionInterface
   *   The SQL conditions.
   */
  protected function mapConditions(ConditionGroup $conditions, Sql $query, array $base_table, array $field_storage_definitions, DefaultTableMapping $table_mapping) {
    $sql_condition = $this->connection->condition($conditions->getConjunction());
    foreach ($conditions->getConditions() as $condition) {
      if ($condition instanceof ConditionGroup) {
        $nested_sql_conditions = $this->mapConditions($condition, $query, $base_table, $field_storage_definitions, $table_mapping);
        $sql_condition->condition($nested_sql_conditions);
      }
      else {
        $field = $condition->getField();
        $property_name = NULL;
        if (strpos($field, '.') !== FALSE) {
          list($field, $property_name) = explode('.', $field);
        }
        // Skip unknown fields.
        if (!isset($field_storage_definitions[$field])) {
          continue;
        }
        $field_storage_definition = $field_storage_definitions[$field];
        if (!$property_name) {
          $property_name = $field_storage_definition->getMainPropertyName();
        }

        $column = $table_mapping->getFieldColumnName($field_storage_definition, $property_name);
        if ($table_mapping->requiresDedicatedTableStorage($field_storage_definitions[$field])) {
          if ($base_table['revision']) {
            $dedicated_table = $table_mapping->getDedicatedRevisionTableName($field_storage_definition);
          }
          else {
            $dedicated_table = $table_mapping->getDedicatedDataTableName($field_storage_definition);
          }
          // Views defaults to LEFT JOIN. For simplicity, we don't try to
          // use an INNER JOIN when it's safe to do so (AND conjunctions).
          $alias = $query->ensureTable($dedicated_table);
        }
        elseif ($base_table['revision'] && !$field_storage_definition->isRevisionable()) {
          // Workaround for #2652652, which causes $query->ensureTable()
          // to not work in this case, due to a missing relationship.
          if ($data_table = $query->getTableInfo($base_table['data_table'])) {
            $alias = $data_table['alias'];
          }
          else {
            $configuration = [
              'type' => 'INNER',
              'table' => $base_table['data_table'],
              'field' => 'id',
              'left_table' => $base_table['alias'],
              'left_field' => 'id',
            ];
            /** @var \Drupal\Views\Plugin\views\join\JoinPluginBase $join */
            $join = Views::pluginManager('join')->createInstance('standard', $configuration);
            $alias = $query->addRelationship($base_table['data_table'], $join, $data_table);
          }
        }
        else {
          $alias = $base_table['alias'];
        }

        $value = $condition->getValue();
        $operator = $condition->getOperator();
        // Using LIKE/NOT LIKE ensures a case insensitive comparison.
        // @see \Drupal\Core\Entity\Query\Sql\Condition::translateCondition().
        $property_definitions = $field_storage_definition->getPropertyDefinitions();
        $case_sensitive = $property_definitions[$property_name]->getSetting('case_sensitive');
        $operator_map = [
          '=' => 'LIKE',
          '<>' => 'NOT LIKE',
        ];
        if ($case_sensitive === FALSE && isset($operator_map[$operator])) {
          $operator = $operator_map[$operator];
          $value = $this->connection->escapeLike($value);
        }

        $sql_condition->condition("$alias.$column", $value, $operator);
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
    if ($request->isMethodCacheable() && $this->renderer->hasRenderContext()) {
      $build = [];
      $cacheable_metadata->applyTo($build);
      $this->renderer->render($build);
    }
  }

}
