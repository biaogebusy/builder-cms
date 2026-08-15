<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\xinshi_ai\Service\ModelRegistryServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * 模型注册中心后台列表:/admin/config/xinshi/ai/models。
 */
final class ModelAdminController extends ControllerBase {

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
   * 模型列表表格(按平台分组展示)。
   */
  public function list(): array {
    $platformLabels = [];
    foreach ($this->registry->getPlatforms() as $platform) {
      $platformLabels[$platform['id']] = $platform['label'] ?? $platform['id'];
    }

    $rows = [];
    foreach ($this->registry->getRegistry()['models'] as $model) {
      $id = (string) ($model['id'] ?? '');
      $label = (string) ($model['label'] ?? $id);
      if (!empty($model['deprecated'])) {
        $label .= ' (' . $this->t('已弃用') . ')';
      }
      if (!($model['enabled'] ?? TRUE)) {
        $label .= ' (' . $this->t('已禁用') . ')';
      }
      $rows[] = [
        'data' => [
          $id,
          $label,
          $platformLabels[$model['platform'] ?? ''] ?? ($model['platform'] ?? ''),
          implode(', ', $model['capabilities'] ?? []),
          (int) ($model['max_n'] ?? 0) ?: '—',
          [
            'data' => [
              '#type' => 'operations',
              '#links' => [
                'edit' => [
                  'title' => $this->t('编辑'),
                  'url' => Url::fromRoute('xinshi_ai.models.edit', ['id' => $id]),
                ],
                'delete' => [
                  'title' => $this->t('删除'),
                  'url' => Url::fromRoute('xinshi_ai.models.delete', ['id' => $id]),
                ],
              ],
            ],
          ],
        ],
      ];
    }

    return [
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('ID'),
          $this->t('名称'),
          $this->t('平台'),
          $this->t('能力'),
          $this->t('最大张数'),
          $this->t('操作'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('尚未注册任何模型。'),
      ],
    ];
  }

}
