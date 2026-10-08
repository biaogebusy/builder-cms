<?php

declare(strict_types=1);

namespace Drupal\xinshi_knowledge_sync\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/** Lists configured sources without reading document bodies. */
final class SourceController extends ControllerBase {

  /** Shows source status and links to the structured configuration form. */
  public function overview(): array {
    $settings = $this->config('xinshi_knowledge_sync.settings');
    $build['intro'] = ['#markup' => '<p>' . $this->t('在这里管理文档来源与阅读范围。保存配置不会立即导入或下架文档；目录规则在下一次同步时应用，来源启停和策略成员变更会立即影响普通读者。') . '</p>'];
    $build['add'] = [
      '#type' => 'link', '#title' => $this->t('添加文档来源'),
      '#url' => Url::fromRoute('xinshi_knowledge_sync.source_add'),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];
    $build['sources'] = [
      '#type' => 'table',
      '#header' => [$this->t('来源标识'), $this->t('状态'), $this->t('默认阅读策略'), $this->t('目录规则'), $this->t('操作')],
      '#empty' => $this->t('尚未配置文档来源，请先添加。'),
    ];
    foreach ($settings->get('sources') ?? [] as $index => $source) {
      $build['sources'][$index] = [
        'id' => ['#plain_text' => $source['id']],
        'enabled' => ['#plain_text' => $source['enabled'] ? $this->t('已启用') : $this->t('已停用')],
        'policy' => ['#plain_text' => $source['default_policy']],
        'rules' => ['#plain_text' => (string) count($source['rules'] ?? [])],
        'edit' => [
          '#type' => 'link', '#title' => $this->t('编辑 @source', ['@source' => $source['id']]),
          '#url' => Url::fromRoute('xinshi_knowledge_sync.source_edit', ['source_id' => $source['id']]),
        ],
      ];
    }
    $build['#cache'] = ['tags' => $settings->getCacheTags(), 'contexts' => ['user.permissions'], 'max-age' => 0];
    return $build;
  }

}
