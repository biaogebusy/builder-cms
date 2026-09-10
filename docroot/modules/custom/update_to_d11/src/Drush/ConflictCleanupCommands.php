<?php

declare(strict_types=1);

namespace Drupal\update_to_d11\Drush;

use Drupal\update_to_d11\MissingModuleCleaner;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * conflict / key_value 模块卸载命令。
 *
 * conflict（仅 beta 支持 D11）与 key_value（不支持 D11）仅为内容锁场景引入，
 * 站点已无启用模块依赖二者，升级 D11 前直接移除。
 */
class ConflictCleanupCommands extends DrushCommands {

  /**
   * 需卸载的模块（module_installer 自动处理卸载顺序）。
   */
  protected const UNINSTALL = ['conflict', 'key_value'];

  /**
   * 各模块持有的配置对象与数据表（文件缺失兜底清理时使用）。
   */
  protected const RESIDUE = [
    'conflict' => ['config' => ['conflict.settings']],
    'key_value' => ['tables' => ['key_value_sorted']],
  ];

  #[CLI\Command(name: 'update-to-d11:conflict-cleanup', description: '卸载 conflict 与 key_value 模块（无启用模块依赖）。')]
  #[CLI\Usage(name: 'drush update-to-d11:conflict-cleanup', description: '执行前请先 drush sql-dump 备份。')]
  public function cleanup(): void {
    if (!$this->io()->confirm('将卸载 conflict、key_value 模块（conflict.settings.yml 配置随之删除），继续？', FALSE)) {
      $this->logger()->warning('已取消。');
      return;
    }
    $enabled = array_filter(
      self::UNINSTALL,
      static fn(string $m): bool => \Drupal::moduleHandler()->moduleExists($m),
    );
    if ($enabled) {
      \Drupal::service('module_installer')->uninstall(array_values($enabled));
    }
    // 文件已随 11.x 部署消失的模块 uninstall() 会静默跳过，
    // 残留的 core.extension 记录触发"缺失模块"警告，此处强制清理。
    $extension_config = $this->configFactory->getEditable('core.extension');
    $purged = [];
    foreach (self::UNINSTALL as $module) {
      if ($extension_config->get("module.$module") !== NULL) {
        MissingModuleCleaner::purge(
          $module,
          self::RESIDUE[$module]['config'] ?? [],
          self::RESIDUE[$module]['tables'] ?? [],
        );
        $purged[] = $module;
      }
    }
    if ($enabled && !$purged) {
      $this->logger()->success('已卸载模块：' . implode(', ', $enabled));
    }
    elseif ($purged) {
      $this->logger()->success(sprintf('文件缺失，已强制清理残留记录：%s。', implode(', ', $purged)));
    }
    if (!$enabled && !$purged) {
      $this->logger()->info('conflict、key_value 均未启用，无需处理。');
    }
    $this->logger()->info('vendor 中的 drupal/conflict、drupal/key_value 包已随 11.x 分支的 composer update 移除，无需再单独执行。');
  }

}
