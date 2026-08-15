<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * 读取 xinshi_ai.models 配置并对外提供归一化的注册中心视图。
 */
final class ModelRegistryService implements ModelRegistryServiceInterface {

  private const CONFIG_NAME = 'xinshi_ai.models';
  private const CID = 'xinshi_ai:registry';

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly CacheBackendInterface $cache,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getRegistry(): array {
    if ($cached = $this->cache->get(self::CID)) {
      return $cached->data;
    }

    $config = $this->configFactory->get(self::CONFIG_NAME);
    $rawPlatforms = $config->get('platforms') ?? [];
    $models = array_values($config->get('models') ?? []);

    // 平台从「id => data」的映射归一化为带 id 的列表。
    $platforms = [];
    foreach ($rawPlatforms as $id => $platform) {
      $platforms[] = ['id' => $id] + (array) $platform;
    }

    // 各模式默认模型:只保留合法模式键与非空字符串值(稀疏,未设置无键)。
    $rawDefaults = $config->get('defaults') ?? [];
    $defaults = [];
    foreach (ModelRegistryServiceInterface::DEFAULT_MODES as $mode) {
      $id = $rawDefaults[$mode] ?? NULL;
      if (is_string($id) && $id !== '') {
        $defaults[$mode] = $id;
      }
    }

    $registry = [
      'version' => $this->hash($rawPlatforms, $models, $defaults),
      'platforms' => $platforms,
      'models' => $models,
      'defaults' => $defaults,
    ];

    // 随配置实体一起失效:config:xinshi_ai.models 在保存时由 Drupal 自动清除。
    $this->cache->set(self::CID, $registry, CacheBackendInterface::CACHE_PERMANENT, [
      'config:' . self::CONFIG_NAME,
    ]);

    return $registry;
  }

  /**
   * {@inheritdoc}
   */
  public function getModelsByCapability(string $capability): array {
    return array_values(array_filter(
      $this->getRegistry()['models'],
      static fn(array $model): bool => in_array($capability, $model['capabilities'] ?? [], TRUE),
    ));
  }

  /**
   * {@inheritdoc}
   */
  public function getModel(string $id): ?array {
    if ($id === '') {
      return NULL;
    }
    $enabled = $this->enabledPlatformIds();
    foreach ($this->getRegistry()['models'] as $model) {
      if (($model['id'] ?? NULL) === $id && in_array($model['platform'] ?? '', $enabled, TRUE)) {
        // 模型级禁用(enabled=false)视为未注册;键缺省为启用。
        return ($model['enabled'] ?? TRUE) ? $model : NULL;
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isValid(string $platform, string $modelId): bool {
    if (!in_array($platform, $this->enabledPlatformIds(), TRUE)) {
      return FALSE;
    }
    $model = $this->getModel($modelId);
    return $model !== NULL && ($model['platform'] ?? NULL) === $platform;
  }

  /**
   * {@inheritdoc}
   */
  public function getVersion(): string {
    return $this->getRegistry()['version'];
  }

  /**
   * 当前 enabled 平台的 id 列表。
   *
   * @return string[]
   */
  private function enabledPlatformIds(): array {
    $ids = [];
    foreach ($this->getRegistry()['platforms'] as $platform) {
      if (!empty($platform['enabled'])) {
        $ids[] = $platform['id'];
      }
    }
    return $ids;
  }

  private function hash(array $platforms, array $models, array $defaults): string {
    return substr(hash('sha256', serialize([$platforms, $models, $defaults])), 0, 12);
  }

  /**
   * {@inheritdoc}
   */
  public function getPlatforms(): array {
    return $this->getRegistry()['platforms'];
  }

  /**
   * {@inheritdoc}
   */
  public function saveModel(array $model, string $originalId = ''): void {
    $config = $this->configFactory->getEditable(self::CONFIG_NAME);
    $models = array_values($config->get('models') ?? []);

    $locate = $originalId !== '' ? $originalId : ($model['id'] ?? '');
    $index = NULL;
    foreach ($models as $i => $existing) {
      if (($existing['id'] ?? NULL) === $locate) {
        $index = $i;
        break;
      }
    }

    if ($index === NULL) {
      $models[] = $model;
    }
    else {
      $models[$index] = $model;
    }

    $config->set('models', array_values($models))->save();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteModel(string $id): void {
    $config = $this->configFactory->getEditable(self::CONFIG_NAME);
    $models = array_values($config->get('models') ?? []);
    $models = array_values(array_filter(
      $models,
      static fn(array $model): bool => ($model['id'] ?? NULL) !== $id,
    ));
    $config->set('models', $models)->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaults(): array {
    return $this->getRegistry()['defaults'];
  }

  /**
   * {@inheritdoc}
   */
  public function saveDefaults(array $defaults): void {
    $config = $this->configFactory->getEditable(self::CONFIG_NAME);
    if ($defaults === []) {
      $config->clear('defaults')->save();
      return;
    }
    $config->set('defaults', $defaults)->save();
  }

}
