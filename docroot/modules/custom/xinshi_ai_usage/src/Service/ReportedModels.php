<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Service;

use Drupal\Core\Database\Connection;

/**
 * Reads the model ids a site has reported in its usage facts.
 *
 * The price book is keyed by the same model id the attempts carry, so these
 * are exactly the names a prefill should price. Historical ids stay listed:
 * an old model still needs a rate when a late event references it.
 */
final class ReportedModels {

  public function __construct(
    private readonly Connection $database,
  ) {}

  /**
   * Distinct reported model ids of one site, most used first.
   *
   * @param string $siteId
   *   The logical site id.
   * @param int $limit
   *   Maximum number of ids to return.
   *
   * @return list<array{model:string,attempts:int}>
   *   Model id and its attempt count.
   */
  public function forSite(string $siteId, int $limit = 200): array {
    if ($siteId === '') {
      return [];
    }
    $query = $this->database->select(UsageProjectionService::ATTEMPT_TABLE, 'a');
    $query->fields('a', ['model_id']);
    $query->condition('a.site_id', $siteId);
    $query->addExpression('COUNT(*)', 'attempts');
    $query->groupBy('a.model_id');
    $query->orderBy('attempts', 'DESC');
    $query->orderBy('a.model_id', 'ASC');
    $query->range(0, max(1, $limit));
    $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    return array_map(static fn(array $row): array => [
      'model' => (string) $row['model_id'],
      'attempts' => (int) $row['attempts'],
    ], $rows);
  }

}
