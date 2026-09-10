<?php

declare(strict_types=1);

namespace Drupal\update_to_d11\Drush;

use Drupal\update_to_d11\PanelizerMigrator;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Panelizer → Layout Builder 迁移命令。
 */
class PanelizerMigrateCommands extends DrushCommands {

  public function __construct(protected PanelizerMigrator $migrator) {
    parent::__construct();
  }

  #[CLI\Command(name: 'update-to-d11:panelizer-migrate', description: '迁移 panelizer 字段数据到 Layout Builder（layout_builder__layout）。')]
  #[CLI\Usage(name: 'drush update-to-d11:panelizer-migrate', description: '执行迁移并输出统计报告。')]
  public function migrate(): void {
    if (!$this->io()->confirm('开始迁移 panelizer 数据到 Layout Builder？')) {
      return;
    }
    $stats = $this->migrator->migrate();
    if (empty($stats['bundles'])) {
      $this->logger()->warning('未发现任何使用 panelizer 字段的内容类型，无需迁移。');
      return;
    }
    $this->io()->section('迁移报告');
    $this->io()->definitionList(
      ['内容类型' => implode(', ', $stats['bundles'])],
      ['已迁移' => (string) $stats['processed']],
      ['跳过（无 panelizer 数据）' => (string) $stats['skipped']],
      ['失败' => (string) $stats['failed']],
    );
    if ($stats['errors']) {
      $this->io()->error('失败明细：');
      $this->io()->listing($stats['errors']);
      throw new \Exception('迁移存在失败项，请处理后重跑。');
    }
    if ($stats['display_log']) {
      $this->io()->section('视图显示迁移日志');
      $this->io()->listing($stats['display_log']);
    }
    $this->logger()->success('迁移完成，请执行 update-to-d11:panelizer-verify 验证。');
  }

  #[CLI\Command(name: 'update-to-d11:panelizer-verify', description: '验证迁移结果：比对 panelizer 与 Layout Builder 的块 uuid 序列。')]
  public function verify(): int {
    $report = $this->migrator->verify();
    if ($report['checked'] === 0) {
      $this->logger()->warning('未发现需要校验的数据（无 panelizer 块数据）。');
      return self::EXIT_SUCCESS;
    }
    $this->io()->section('验证报告');
    $this->io()->definitionList(
      ['校验数' => (string) $report['checked']],
      ['一致' => (string) $report['ok']],
      ['缺失布局' => (string) count($report['missing'])],
      ['不一致' => (string) count($report['mismatch'])],
    );
    if ($report['missing']) {
      $this->io()->error('缺失 Layout Builder 布局：');
      $this->io()->listing($report['missing']);
    }
    if ($report['mismatch']) {
      $this->io()->error('块序列不一致：');
      $this->io()->listing($report['mismatch']);
    }
    if ($report['display_residue']) {
      $this->io()->error('视图显示仍残留 panelizer 设置：');
      $this->io()->listing($report['display_residue']);
    }
    if ($report['missing'] || $report['mismatch'] || $report['display_residue']) {
      return self::EXIT_FAILURE;
    }
    $this->logger()->success('全部一致，可执行 update-to-d11:panelizer-cleanup 清理。');
    return self::EXIT_SUCCESS;
  }

  #[CLI\Command(name: 'update-to-d11:panelizer-cleanup', description: '清理 panelizer：删除字段与数据、ECK panelizer_display_attribute、卸载相关模块。不可逆。')]
  #[CLI\Usage(name: 'drush update-to-d11:panelizer-cleanup', description: '执行前请先 drush sql-dump 备份。')]
  public function cleanup(): void {
    if (!$this->io()->confirm('将不可逆地删除 panelizer 字段数据、ECK panelizer_display_attribute 并卸载 panelizer/panels/panels_ipe/ctools_block，请确认已执行 drush sql-dump 备份。继续？', FALSE)) {
      $this->logger()->warning('已取消。');
      return;
    }
    $log = $this->migrator->cleanup();
    $this->io()->section('清理日志');
    $this->io()->listing($log);
    $this->logger()->success('清理完成。');
  }

}
