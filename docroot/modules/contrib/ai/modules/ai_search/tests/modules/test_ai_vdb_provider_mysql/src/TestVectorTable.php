<?php
// phpcs:ignoreFile -- This is intended to match the file its extending as close
// as possible, and therefore avoids coding standards changes from source.

namespace Drupal\test_ai_vdb_provider_mysql;

use MHz\MysqlVector\VectorTable;

class TestVectorTable extends VectorTable {

  private VectorTable $vectorTable;

  public function __construct(VectorTable $vectorTable)
  {
    $this->vectorTable = $vectorTable;
  }

  public function search(array $vector, int $n = 10): array
  {
    // Access the private mysqli property
    $reflectionClass = new \ReflectionClass($this->vectorTable);
    $mysqliProperty = $reflectionClass->getProperty('mysqli');
    $mysqliProperty->setAccessible(true);
    $mysqli = $mysqliProperty->getValue($this->vectorTable);

    // Access the private normalize method
    $normalizeMethod = $reflectionClass->getMethod('normalize');
    $normalizeMethod->setAccessible(true);
    $normalizedVector = $normalizeMethod->invoke($this->vectorTable, $vector);

    $tableName = $this->vectorTable->getVectorTableName();
    $mapTableName = str_replace('_vectors', '_map', $tableName);
    $binaryCode = $this->vectorTable->vectorToHex($normalizedVector);

    // Initial search using binary codes
    $statement = $mysqli->prepare("
        SELECT 
            derived.vector_id AS id,
            derived.hamming_distance,
            derived.drupal_entity_id,
            _map.drupal_long_id
        FROM (
            SELECT 
                _map.drupal_entity_id,
                _vec.id AS vector_id,
                BIT_COUNT(_vec.binary_code ^ UNHEX(?)) AS hamming_distance
            FROM $tableName AS _vec
            INNER JOIN $mapTableName AS _map ON _vec.id = _map.vector_table_id
            WHERE _vec.binary_code IS NOT NULL
            ORDER BY hamming_distance ASC
        ) AS derived
        INNER JOIN $mapTableName AS _map ON _map.vector_table_id = derived.vector_id
            AND _map.drupal_entity_id = derived.drupal_entity_id
        GROUP BY derived.drupal_entity_id, derived.vector_id, derived.hamming_distance, _map.drupal_long_id
        ORDER BY derived.hamming_distance ASC
        LIMIT $n
    ");
    $statement->bind_param('s', $binaryCode);

    if (!$statement) {
      throw new \Exception($mysqli->error);
    }

    $statement->execute();
    $statement->bind_result($vectorId, $hd, $drupal_entity_id, $drupal_long_id);

    $candidates = [];
    while ($statement->fetch()) {
      $candidates[] = $vectorId;
    }
    $statement->close();

    if (empty($candidates)) {
      return [];
    }

    // Rerank candidates with inner join and grouping logic
    $placeholders = implode(',', array_fill(0, count($candidates), '?'));
    $sql = "
        SELECT 
            _vec.id, 
            _vec.vector, 
            _vec.normalized_vector, 
            _vec.magnitude, 
            MAX(COSIM(_vec.normalized_vector, ?)) AS similarity,
            _map.drupal_entity_id,
            _map.drupal_long_id
        FROM $tableName AS _vec
        INNER JOIN $mapTableName AS _map ON _vec.id = _map.vector_table_id
        WHERE _vec.id IN ($placeholders)
        GROUP BY 
            _map.drupal_entity_id, 
            _vec.id, 
            _vec.vector, 
            _vec.normalized_vector, 
            _vec.magnitude, 
            _map.drupal_long_id
        ORDER BY similarity DESC
        LIMIT $n
    ";

    $statement = $mysqli->prepare($sql);

    if (!$statement) {
      throw new \Exception($mysqli->error);
    }

    $normalizedVector = json_encode($normalizedVector);

    $types = str_repeat('i', count($candidates));
    $statement->bind_param('s' . $types, $normalizedVector, ...$candidates);

    $statement->execute();

    $statement->bind_result($id, $v, $nv, $mag, $sim, $drupal_entity_id, $drupal_long_id);

    // Add the drupal entity details and normalize the similarity score to
    // match where it is stored in other providers.
    while ($statement->fetch()) {
      $results[] = [
        'id' => $id,
        'vector' => json_decode($v, true),
        'normalized_vector' => json_decode($nv, true),
        'magnitude' => $mag,
        'similarity' => $sim,
        'distance' => $sim,
        'drupal_entity_id' => $drupal_entity_id,
        'drupal_long_id' => $drupal_long_id,
      ];
    }

    $statement->close();

    return $results;
  }

}