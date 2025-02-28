<?php

namespace Drupal\views_table_rowspan\Plugin\views\style;

use Drupal\views\Plugin\views\style\Table;
use Drupal\Core\Form\FormStateInterface;

/**
 * Style plugin to merge duplicate row in table.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "table_rowspan",
 *   title = @Translation("Table Rowspan"),
 *   help = @Translation("Merge duplicate rows in group use rowspan
 *   attribute."), theme = "views_view_table", display_types = {"normal"}
 * )
 */
class TableRowSpan extends Table {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['rowspan'] = ['default' => TRUE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['rowspan'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Merge rows in table'),
      '#description' => $this->t('Merge rows table that has same value (in a same group) use attribute @url', ['@url' => 'http://www.w3schools.com/tags/att_td_rowspan.asp']),
      '#default_value' => $this->options['rowspan'],
      '#weight' => 0,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function renderGroupingSets($sets) {
    if (!empty($this->options['grouping']) && !empty($this->options['rowspan'])) {
      $rows = $this->getColSpanRows($sets);
      $sets = [
        [
          'group' => '',
          'rows' => $rows,
        ],
      ];
      // Convert sets to one group.
      $this->options['grouping'] = [];
    }
    return parent::renderGroupingSets($sets);
  }

  /**
   * Convert grouping sets into table rows.
   *
   * @param array $sets
   *   Views grouping sets.
   * @param int $level
   *   Views grouping level.
   * @param mixed $parent
   *   Views grouping parent.
   *
   * @return array
   *   An array of rows in table.
   */
  protected function getColSpanRows(array $sets = [], $level = 0, $parent = NULL) {
    $rows = [];
    $leaf_rows = [];
    $group_field_name = $this->options['grouping'][$level]['field'];
    foreach ($sets as $set) {
      $new_level = $level + 1;

      $leaf_rows = $this->getDeepestRows($set);
      $leaf_rows_index = array_keys($leaf_rows);
      $first_index = $leaf_rows_index[0];
      $this->view->rowspan[$group_field_name][$first_index] = $leaf_rows_index;
      $row = reset($set['rows']);

      if (is_array($row) && isset($row['group'])) {
        $rows += $this->getColSpanRows($set['rows'], $new_level, $set);
      }
      else {
        foreach ($set['rows'] as $index => $set_row) {
          $rows[$index] = $set_row;
        }
      }
    }
    return $rows;
  }

  /**
   * Get deepest rows in a group.
   *
   * @param array $set
   *   View grouping set.
   */
  protected function getDeepestRows(array $set = []) {
    $row = reset($set['rows']);
    // Check set is a group or a row.
    if (is_array($row) && isset($row['group'])) {
      $result = [];
      foreach ($set['rows'] as $sub_set) {
        $subset_result = $this->getDeepestRows($sub_set);
        $result += $subset_result;
      }
      return $result;
    }
    else {
      $_result = [];
      foreach ($set['rows'] as $row_index => $row) {
        $_result[$row_index] = $row;
      }
      return $_result;
    }
  }

}
