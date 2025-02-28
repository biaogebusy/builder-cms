<?php

namespace Drupal\image_effects\Component;

/**
 * Matrix handling methods for image_effects.
 */
abstract class MatrixUtility {

  /**
   * Calculates the cumulative sum matrix of a bi-dimensional matrix.
   *
   * @param array $matrix
   *   A simple array of matrix rows values. Each row is a simple array of
   *   numeric values.
   *
   * @return array
   *   A matrix representing the cumulative sum of the input matrix, in the form
   *   of a simple array of matrix rows values. Each row is a simple array of
   *   numeric values.
   *
   * @see https://stackoverflow.com/questions/39937212/maximum-subarray-of-size-hxw-within-a-2d-matrix
   */
  public static function cumulativeSum(array $matrix) {
    $matrix_rows = count($matrix);
    $matrix_columns = count($matrix[0]);

    $cumulative_sum_matrix = [];

    for ($r = 0; $r < $matrix_rows; $r++) {
      for ($c = 0; $c < $matrix_columns; $c++) {
        if ($r === 0 && $c === 0) {
          $cumulative_sum_matrix[$r][$c] = $matrix[$r][$c];
        }
        elseif ($r == 0) {
          $cumulative_sum_matrix[$r][$c] = $cumulative_sum_matrix[$r][$c - 1] + $matrix[$r][$c];
        }
        elseif ($c == 0) {
          $cumulative_sum_matrix[$r][$c] = $cumulative_sum_matrix[$r - 1][$c] + $matrix[$r][$c];
        }
        else {
          $cumulative_sum_matrix[$r][$c] = $cumulative_sum_matrix[$r - 1][$c] + $cumulative_sum_matrix[$r][$c - 1] - $cumulative_sum_matrix[$r - 1][$c - 1] + $matrix[$r][$c];
        }
      }
    }

    return $cumulative_sum_matrix;
  }

  /**
   * Find the sum-matrix of a bi-dimensional matrix having the highest sum.
   *
   * @param array $matrix
   *   A simple array of matrix rows values. Each row is a simple array of
   *   numeric values.
   * @param int $rows
   *   The number of rows of the sub-matrix.
   * @param int $columns
   *   The number of columns of the sub-matrix.
   *
   * @return array
   *   A simple array with the following values:
   *   - the row of the input matrix where the sub-matrix starts;
   *   - the column of the input matrix where the sub-matrix starts;
   *   - the value of the sum of the sub-matrix.
   *
   * @see https://stackoverflow.com/questions/39937212/maximum-subarray-of-size-hxw-within-a-2d-matrix
   */
  public static function findMaxSumSubmatrix(array $matrix, $rows, $columns) {
    $matrix_rows = count($matrix);
    $matrix_columns = count($matrix[0]);

    $max_sum = 0;
    $max_sum_position = NULL;

    for ($r1 = 0; $r1 < $matrix_rows; $r1++) {
      for ($c1 = 0; $c1 < $matrix_columns; $c1++) {
        $r2 = $r1 + $rows - 1;
        $c2 = $c1 + $columns - 1;
        if ($r2 >= $matrix_rows || $c2 >= $matrix_columns) {
          continue;
        }
        if ($r1 == 0 && $c1 == 0) {
          $sub_sum = $matrix[$r2][$c2];
        }
        elseif ($r1 == 0) {
          $sub_sum = $matrix[$r2][$c2] - $matrix[$r2][$c1 - 1];
        }
        elseif ($c1 == 0) {
          $sub_sum = $matrix[$r2][$c2] - $matrix[$r1 - 1][$c2];
        }
        else {
          $sub_sum = $matrix[$r2][$c2] - $matrix[$r1 - 1][$c2] - $matrix[$r2][$c1 - 1] + $matrix[$r1 - 1][$c1 - 1];
        }
        if ($max_sum < $sub_sum) {
          $max_sum_position = [$r1, $c1, $sub_sum];
          $max_sum = $sub_sum;
        }
      }
    }

    return $max_sum_position;
  }

}
