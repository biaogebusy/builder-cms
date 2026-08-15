<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\xinshi_ai\Service\ModelRegistryServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * 模型注册中心管理 API:新增 / 更新 / 启停模型(详见模块文档 §5)。
 *
 * 与后台表单(ModelForm)读写同一份 xinshi_ai.models 配置,
 * 供管理前台(SPA)以 administer xinshi_ai 权限调用。
 */
final class ModelManageController extends ControllerBase {

  /** 受支持的能力枚举(与 ModelForm::CAPABILITIES 的键一致)。 */
  private const CAPABILITIES = ['chat', 'reasoning', 'image', 'image-edit'];

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
   * GET /api/v3/ai/manage/models — 全量注册中心(含禁用的平台与模型)。
   */
  public function list(): JsonResponse {
    $registry = $this->registry->getRegistry();
    return new JsonResponse([
      'version' => $registry['version'],
      'platforms' => $registry['platforms'],
      'models' => $registry['models'],
      // 原始映射(不过滤启用状态),空映射需序列化为 {} 而非 []。
      'defaults' => (object) $registry['defaults'],
    ]);
  }

  /**
   * PATCH /api/v3/ai/manage/defaults — 部分更新各模式默认模型。
   *
   * body 形如 {"chat": "deepseek-v4-pro"};值传 null / "" 清除该模式。
   * 指向的模型必须已启用且具备对应能力,否则 422(errors 按模式键返回)。
   */
  public function updateDefaults(Request $request): JsonResponse {
    $payload = json_decode($request->getContent(), TRUE);
    if (!is_array($payload)) {
      return new JsonResponse(['error' => 'Invalid JSON body.'], 400);
    }

    $defaults = $this->registry->getDefaults();
    $errors = [];
    foreach ($payload as $mode => $id) {
      if (!in_array($mode, ModelRegistryServiceInterface::DEFAULT_MODES, TRUE)) {
        $errors[(string) $mode] = sprintf('mode must be one of: %s.', implode(', ', ModelRegistryServiceInterface::DEFAULT_MODES));
        continue;
      }
      if ($id === NULL || $id === '') {
        unset($defaults[$mode]);
        continue;
      }
      if (!is_string($id)) {
        $errors[$mode] = 'model id must be a string or null.';
        continue;
      }
      // getModel 已过滤平台禁用 / 模型禁用;能力须与模式同名。
      $model = $this->registry->getModel($id);
      if ($model === NULL || !in_array($mode, $model['capabilities'] ?? [], TRUE)) {
        $errors[$mode] = sprintf("Model '%s' must be an enabled model with the '%s' capability.", $id, $mode);
        continue;
      }
      $defaults[$mode] = $id;
    }
    if ($errors) {
      return new JsonResponse(['errors' => $errors], 422);
    }

    // 按固定模式顺序归一化,保证配置导出 diff 稳定。
    $normalized = [];
    foreach (ModelRegistryServiceInterface::DEFAULT_MODES as $mode) {
      if (isset($defaults[$mode])) {
        $normalized[$mode] = $defaults[$mode];
      }
    }

    $this->registry->saveDefaults($normalized);
    return new JsonResponse(['defaults' => (object) $normalized]);
  }

  /**
   * POST /api/v3/ai/models — 新增模型。
   */
  public function add(Request $request): JsonResponse {
    $payload = json_decode($request->getContent(), TRUE);
    if (!is_array($payload)) {
      return new JsonResponse(['error' => 'Invalid JSON body.'], 400);
    }

    [$model, $errors] = $this->normalizeModel($payload);
    if ($errors) {
      return new JsonResponse(['errors' => $errors], 422);
    }
    if ($this->findModel($model['id']) !== NULL) {
      return new JsonResponse(['error' => sprintf("Model '%s' already exists.", $model['id'])], 409);
    }

    $this->registry->saveModel($model);
    return new JsonResponse(['model' => $model], 201);
  }

  /**
   * PATCH /api/v3/ai/models/{id} — 部分更新;{"enabled": false} 即禁用。
   */
  public function update(string $id, Request $request): JsonResponse {
    $existing = $this->findModel($id);
    if ($existing === NULL) {
      return new JsonResponse(['error' => 'Model not found.'], 404);
    }

    $payload = json_decode($request->getContent(), TRUE);
    if (!is_array($payload)) {
      return new JsonResponse(['error' => 'Invalid JSON body.'], 400);
    }
    if (array_key_exists('id', $payload) && $payload['id'] !== $id) {
      return new JsonResponse(['error' => 'Model id cannot be changed.'], 400);
    }

    // 部分更新:未提交的字段沿用现值;显式传 null / 空可清除可选字段。
    [$model, $errors] = $this->normalizeModel(array_replace($existing, $payload, ['id' => $id]));
    if ($errors) {
      return new JsonResponse(['errors' => $errors], 422);
    }

    $this->registry->saveModel($model, $id);
    return new JsonResponse(['model' => $model]);
  }

  /**
   * 校验并归一化模型条目。
   *
   * @return array{0: array, 1: array}
   *   [模型条目, 错误数组];错误非空时模型条目不可用。
   */
  private function normalizeModel(array $input): array {
    $errors = [];

    $id = is_scalar($input['id'] ?? NULL) ? trim((string) $input['id']) : '';
    if ($id === '' || strlen($id) > 128 || !preg_match('/^[a-z0-9_.\-]+$/', $id)) {
      $errors['id'] = 'id is required and may only contain a-z 0-9 _ . - (max 128 chars).';
    }

    $label = is_scalar($input['label'] ?? NULL) ? trim((string) $input['label']) : '';
    if ($label === '' || mb_strlen($label) > 255) {
      $errors['label'] = 'label is required (max 255 chars).';
    }

    $platformIds = array_column($this->registry->getPlatforms(), 'id');
    $platform = is_scalar($input['platform'] ?? NULL) ? (string) $input['platform'] : '';
    if (!in_array($platform, $platformIds, TRUE)) {
      $errors['platform'] = sprintf('platform must be one of: %s.', implode(', ', $platformIds));
    }

    $capabilities = is_array($input['capabilities'] ?? NULL)
      ? array_values(array_unique(array_filter($input['capabilities'], 'is_string')))
      : [];
    if ($capabilities === [] || array_diff($capabilities, self::CAPABILITIES) !== []) {
      $errors['capabilities'] = sprintf('capabilities must be a non-empty array of: %s.', implode(', ', self::CAPABILITIES));
    }

    $model = [
      'id' => $id,
      'label' => $label,
      'platform' => $platform,
      'capabilities' => $capabilities,
    ];

    if (isset($input['max_n']) && $input['max_n'] !== '') {
      if (!is_numeric($input['max_n']) || (int) $input['max_n'] < 1) {
        $errors['max_n'] = 'max_n must be a positive integer.';
      }
      else {
        $model['max_n'] = (int) $input['max_n'];
      }
    }

    if (isset($input['sizes']) && $input['sizes'] !== []) {
      if (!is_array($input['sizes'])) {
        $errors['sizes'] = 'sizes must be an array of strings, e.g. ["1024x1024"].';
      }
      else {
        $sizes = array_values(array_filter(
          array_map(static fn($size): string => is_scalar($size) ? trim((string) $size) : '', $input['sizes']),
          static fn(string $size): bool => $size !== '',
        ));
        if ($sizes) {
          $model['sizes'] = $sizes;
        }
      }
    }

    if (!empty($input['deprecated'])) {
      $model['deprecated'] = TRUE;
    }
    // enabled 缺省为启用;仅显式禁用时落库(与种子数据保持最小键集)。
    if (array_key_exists('enabled', $input) && $input['enabled'] !== NULL && !$input['enabled']) {
      $model['enabled'] = FALSE;
    }
    $notes = is_scalar($input['notes'] ?? NULL) ? trim((string) $input['notes']) : '';
    if ($notes !== '') {
      $model['notes'] = $notes;
    }

    return [$model, $errors];
  }

  /**
   * 原样查找模型(不过滤平台 / 模型的 enabled 状态)。
   */
  private function findModel(string $id): ?array {
    if ($id === '') {
      return NULL;
    }
    foreach ($this->registry->getRegistry()['models'] as $model) {
      if (($model['id'] ?? NULL) === $id) {
        return $model;
      }
    }
    return NULL;
  }

}
