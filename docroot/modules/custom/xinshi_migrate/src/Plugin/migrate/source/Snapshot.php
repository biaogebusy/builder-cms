<?php

namespace Drupal\xinshi_migrate\Plugin\migrate\source;

use Drupal\migrate\Attribute\MigrateSource;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;
use Drupal\Core\Database\Query\ConditionInterface;
use Drupal\xinshi_migrate\SnapshotPlan;

/** Reads original SQL values, including serialized layouts and every revision. */
#[MigrateSource(id: 'xinshi_snapshot')]
final class Snapshot extends SqlBase {

  private function plan(): SnapshotPlan {
    return \Drupal::service('xinshi_migrate.plan');
  }

  public function query() {
    $table = $this->configuration['table_name'];
    $specification = $this->plan()->table($table);
    $query = $this->select($table, 's')->fields('s', $specification['columns']);
    $this->addConditions($query, $specification['conditions'] ?? []);
    foreach (array_keys($specification['ids']) as $column) {
      $query->orderBy('s.' . $column);
    }
    return $query;
  }

  private function addConditions(ConditionInterface $query, array $conditions): void {
    foreach ($conditions as $condition) {
      if (isset($condition['conjunction'])) {
        if (!in_array($condition['conjunction'], ['AND', 'OR'], TRUE)) {
          throw new \RuntimeException('Invalid condition group in the snapshot plan.');
        }
        $group = $condition['conjunction'] === 'OR' ? $query->orConditionGroup() : $query->andConditionGroup();
        $this->addConditions($group, $condition['conditions']);
        $query->condition($group);
        continue;
      }
      if ($condition['operator'] === 'IN' && !$condition['value']) {
        $query->where('1 = 0');
      }
      else {
        $query->condition('s.' . $condition['column'], $condition['value'], $condition['operator']);
      }
    }
  }

  public function fields(): array {
    $columns = $this->plan()->table($this->configuration['table_name'])['columns'];
    return array_combine($columns, $columns);
  }

  public function getIds(): array {
    $ids = $this->plan()->table($this->configuration['table_name'])['ids'];
    foreach ($ids as &$id) {
      $id['alias'] = 's';
    }
    return $ids;
  }

  public function prepareRow(Row $row): bool {
    $record = [];
    foreach (array_keys($this->fields()) as $column) {
      $record[$column] = $row->getSourceProperty($column);
    }
    $row->setSourceProperty('record', $record);
    return parent::prepareRow($row);
  }

}
