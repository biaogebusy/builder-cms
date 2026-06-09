<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

/**
 * AI 平台与模型注册中心读取接口。
 *
 * 数据源:xinshi_ai.models config(详见模块文档 §5)。
 */
interface ModelRegistryServiceInterface {

  /**
   * 全量注册中心(供 admin 与 /api/v3/ai/models 使用)。
   *
   * @return array
   *   ['platforms' => array, 'models' => array, 'version' => string]
   */
  public function getRegistry(): array;

  /**
   * 按能力过滤模型。
   *
   * @return array[]
   *   命中的模型条目数组。
   */
  public function getModelsByCapability(string $capability): array;

  /**
   * 按 id 取单个模型;未注册或所属平台 enabled=false 返回 NULL。
   */
  public function getModel(string $id): ?array;

  /**
   * 校验 platform + model 是否合法且 enabled。
   */
  public function isValid(string $platform, string $modelId): bool;

  /**
   * 注册中心版本号(由内容 hash 派生),供前端缓存 ETag。
   */
  public function getVersion(): string;

  /**
   * 平台列表(归一化为带 id 的数组)。
   *
   * @return array[]
   *   每项含 id / label / enabled / auth 等。
   */
  public function getPlatforms(): array;

  /**
   * 新增或更新一个模型(按 id 定位;originalId 用于改动 id 时定位旧记录)。
   *
   * @param array $model
   *   模型条目(id / label / platform / capabilities / max_n / sizes / ...)。
   * @param string $originalId
   *   编辑前的原 id;新增时传空。
   */
  public function saveModel(array $model, string $originalId = ''): void;

  /**
   * 按 id 删除一个模型。
   */
  public function deleteModel(string $id): void;

}
