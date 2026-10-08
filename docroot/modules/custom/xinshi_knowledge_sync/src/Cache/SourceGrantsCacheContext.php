<?php

declare(strict_types=1);

namespace Drupal\xinshi_knowledge_sync\Cache;

use Drupal\Core\Cache\Context\CalculatedCacheContextInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;

/** Varies node-query caches when source policy or account membership changes. */
final class SourceGrantsCacheContext implements CalculatedCacheContextInterface {

  public function __construct(
    private readonly CalculatedCacheContextInterface $inner,
    private readonly ConfigFactoryInterface $config,
    private readonly AccountProxyInterface $account,
  ) {}

  /** {@inheritdoc} */
  public static function getLabel() {
    return t('Content and synchronized document access');
  }

  /** {@inheritdoc} */
  public function getContext($parameter = NULL) {
    return $this->inner->getContext($parameter) . ':sync:' . hash('sha256', serialize([
      $this->account->id(), $this->account->getRoles(),
      $this->account->hasPermission('view xinshi knowledge'),
      $this->config->get('xinshi_knowledge_sync.settings')->get('sources'),
    ]));
  }

  /** {@inheritdoc} */
  public function getCacheableMetadata($parameter = NULL) {
    return $this->inner->getCacheableMetadata($parameter)->setCacheMaxAge(0);
  }

}
