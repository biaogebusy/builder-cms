<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Contract;

/**
 * Fetches a model price catalog used to prefill price book drafts.
 *
 * Implementations return the decoded catalog: a map of model id to entry, as
 * published by the upstream source. The catalog is assistive input only: it
 * fills the admin form and never creates or activates price book versions.
 */
interface PriceCatalogFetcher {

  /**
   * Returns the decoded catalog: model id => entry array.
   *
   * @return array<string, array>
   *
   * @throws \Drupal\xinshi_ai_usage\Service\PriceBookException
   *   When the catalog cannot be fetched or is not the expected shape.
   */
  public function fetchCatalog(): array;

}
