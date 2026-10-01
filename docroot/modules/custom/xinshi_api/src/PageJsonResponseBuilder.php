<?php

namespace Drupal\xinshi_api;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\VariationCacheInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/** Builds page JSON with the cacheability of its access and rendered content. */
class PageJsonResponseBuilder {

  public function __construct(
    private readonly ConfigFactoryInterface $configs,
    private readonly VariationCacheInterface $cache,
    private readonly RendererInterface $renderer,
    private readonly ModuleHandlerInterface $modules,
    private readonly AccountInterface $account,
    private readonly TimeInterface $time,
    private readonly LoggerInterface $logger,
  ) {}

  /** Preserves the page endpoint's data and fallback response formats. */
  public function build(?EntityInterface $entity, Request $request): CacheableJsonResponse {
    $config = $this->configs->get('xinshi_api.settings');
    $metadata = (new CacheableMetadata())->addCacheableDependency($config)
      ->addCacheContexts(['url', 'user.permissions', 'languages:language_interface', 'languages:language_content']);
    if (!$entity) {
      return $this->response(JsonAPIUtil::notFound(), $metadata->setCacheMaxAge(0));
    }
    $access = $entity->access('view', $this->account, TRUE);
    $metadata->addCacheableDependency($entity)->addCacheableDependency($access);
    if (!$access->isAllowed()) {
      return $this->response(JsonAPIUtil::accessDenied(), $metadata->setCacheMaxAge(0));
    }

    // Personalized and unpublished output must not enter a persistent page cache.
    if ($this->account->isAuthenticated()) {
      $metadata->addCacheContexts(['user'])->setCacheMaxAge(0);
    }
    if (($entity instanceof EntityPublishedInterface && !$entity->isPublished())
      || $config->get('debug') || $this->bypassCache($request)) {
      $metadata->setCacheMaxAge(0);
    }
    $cache_config = $config->get($entity->getEntityTypeId() . '_cache') ?? [];
    if ($cache_config[$entity->bundle()]['context']['user'] ?? FALSE) {
      $metadata->addCacheContexts(['user']);
    }
    $mode = $request->get('mode') ?? 'json';
    $mode = is_string($mode) ? $mode : 'json';
    // A new namespace prevents reading incomplete entries written by the old code.
    $keys = ['xinshi:page-json:v2', $mode, $entity->getEntityTypeId(), $entity->id(),
      $entity->language()->getId(), $entity instanceof RevisionableInterface ? $entity->getRevisionId() : 'none'];
    $initial = clone $metadata;
    $cache_enabled = $config->get('cache_enable') && $metadata->getCacheMaxAge() !== 0;
    if ($cache_enabled && ($item = $this->cache->get($keys, $initial))) {
      $metadata = $metadata->merge($item->data['metadata'])->addCacheTags($item->tags);
      if ($item->expire !== Cache::PERMANENT) {
        // A late hit must not restart the lifetime of time-sensitive rendered data.
        $metadata->setCacheMaxAge(Cache::mergeMaxAges($metadata->getCacheMaxAge(), max(0, $item->expire - $this->time->getRequestTime())));
      }
      return $this->response($item->data['data'], $metadata);
    }

    try {
      $context = new RenderContext();
      $data = $this->renderer->executeInRenderContext($context, function () use ($entity, $mode, &$metadata) {
        $json = match ($entity->getEntityTypeId()) {
          'node' => new NodeJson($entity, $mode),
          'taxonomy_term' => new TermJson($entity, $mode),
          'user' => new UserJson($entity, $mode),
          default => new EntityJsonBase($entity, $mode),
        };
        $data = $json->getContent();
        $metadata->addCacheableDependency($json);
        // Altered output and its dependencies must be identical on misses and hits.
        $this->modules->alter('xinshi_api_data', $data, $entity, $metadata);
        return $data;
      });
      if (!$context->isEmpty()) {
        $metadata = $metadata->merge($context->pop());
      }
      if ($cache_enabled) {
        $this->cache->set($keys, ['data' => $data, 'metadata' => $metadata], $metadata, $initial);
      }
      return $this->response($data, $metadata);
    }
    catch (\Exception $exception) {
      $this->logger->error('Page JSON rendering failed for @type:@id (@exception).', [
        '@type' => $entity->getEntityTypeId(), '@id' => $entity->id(), '@exception' => get_class($exception),
      ]);
      return $this->response([], $metadata->setCacheMaxAge(0));
    }
  }

  /** Recognizes the existing browser callers' explicit cache bypass flags. */
  private function bypassCache(Request $request): bool {
    $query = $request->query->all();
    foreach (['noCache', 'nocache', 'preview'] as $flag) {
      if (filter_var($query[$flag] ?? FALSE, FILTER_VALIDATE_BOOLEAN)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  private function response($data, CacheableMetadata $metadata): CacheableJsonResponse {
    $response = new CacheableJsonResponse($data);
    $response->addCacheableDependency($metadata);
    return $response;
  }

}
