<?php

declare(strict_types=1);

namespace Drupal\update_to_d11\Drush;

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
      $this->logger()->success('已卸载模块：' . implode(', ', $enabled));
    }
    else {
      $this->logger()->info('conflict、key_value 均未启用，无需处理。');
    }
    $this->logger()->info('后续步骤：容器内执行 composer update drupal/conflict drupal/key_value 移除 vendor 包。');
  }

}
