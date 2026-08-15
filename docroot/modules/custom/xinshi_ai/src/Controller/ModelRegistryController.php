<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\xinshi_ai\Service\ModelRegistryServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * 对外只读暴露模型注册中心:GET /api/v3/ai/models。
 *
 * 响应只包含前端需要的字段;base_url / api_key_env 等后端内部字段在此剔除。
 */
final class ModelRegistryController extends ControllerBase {

  public function __construct(
    private readonly ModelRegistryServiceInterface $registry,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('xinshi_ai.model_registry'));
  }

  /**
   * GET /api/v3/ai/models。
   */
  public function list(Request $request): CacheableJsonResponse {
    $registry = $this->registry->getRegistry();

    $platforms = [];
    foreach ($registry['platforms'] as $platform) {
      // enabled=false 的平台不对外暴露。
      if (empty($platform['enabled'])) {
        continue;
      }
      $entry = [
        'id' => $platform['id'],
        'label' => $platform['label'] ?? $platform['id'],
      ];
      if (!empty($platform['user_supplied'])) {
        $entry['user_supplied'] = TRUE;
      }
      $platforms[] = $entry;
    }

    $capability = $request->query->get('capability');
    $models = $capability
      ? $this->registry->getModelsByCapability((string) $capability)
      : $registry['models'];

    // enabled=false 的模型不对外暴露(键缺省为启用)。
    $models = array_filter($models, static fn(array $model): bool => (bool) ($model['enabled'] ?? TRUE));

    $payload = [
      'version' => $registry['version'],
      'platforms' => $platforms,
      'models' => array_values($models),
    ];

    // 各模式默认模型:只暴露公开可用(平台+模型均启用)且能力匹配的条目;
    // 无有效条目时整键省略,前端回落到本地兜底默认。
    $defaults = [];
    foreach ($registry['defaults'] as $mode => $id) {
      $model = $this->registry->getModel((string) $id);
      if ($model !== NULL && in_array($mode, $model['capabilities'] ?? [], TRUE)) {
        $defaults[$mode] = $id;
      }
    }
    if ($defaults !== []) {
      $payload['defaults'] = $defaults;
    }

    $response = new CacheableJsonResponse($payload);
    $response->addCacheableDependency(
      (new CacheableMetadata())
        ->addCacheTags(['config:xinshi_ai.models'])
        ->addCacheContexts(['url.query_args:capability'])
    );
    return $response;
  }

}
