<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Service;

/** Optional sales projection for operation IDs already scoped by a usage report. */
interface UsageChargesInterface {

  /** Summarize complete matching operations; a user scope must also match the ledger owner. */
  public function summarize(string $siteId, array $operationIds, ?string $actorUserId = NULL): array;

}
