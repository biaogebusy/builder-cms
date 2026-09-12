<?php

declare(strict_types=1);

namespace Drupal\update_to_d11\Drush;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * D11 移除/弃用模块与 standard profile 清理命令。
 *
 * color / rdf（D10 已出 core 的 contrib 回退）、switch_page_theme（无 D11 版本）
 * 统一卸载；standard profile 在 D11 弃用，切换到 minimal。
 */
class LegacyCleanupCommands extends DrushCommands {

  /**
   * 需卸载的模块（module_installer 自动处理卸载顺序）。
   */
  protected const UNINSTALL = [
    'color',
    'rdf',
    'switch_page_theme',
  ];

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ModuleHandlerInterface $moduleHandler,
    protected ModuleInstallerInterface $moduleInstaller,
  ) {
    parent::__construct();
  }

  #[CLI\Command(name: 'update-to-d11:legacy-cleanup', description: '卸载 color/rdf/switch_page_theme，并把 standard profile 切到 minimal。')]
  #[CLI\Usage(name: 'drush update-to-d11:legacy-cleanup', description: '执行前请先 drush sql-dump 备份。')]
  public function cleanup(): void {
    if (!$this->io()->confirm('将卸载 color、rdf、switch_page_theme 模块，并把安装 profile 从 standard 切到 minimal，继续？', FALSE)) {
      $this->logger()->warning('已取消。');
      return;
    }

    $enabled = array_filter(
      self::UNINSTALL,
      fn(string $m): bool => $this->moduleHandler->moduleExists($m),
    );
    if ($enabled) {
      $this->moduleInstaller->uninstall(array_values($enabled));
      $this->logger()->success('已卸载模块：' . implode(', ', $enabled));
    }
    else {
      $this->logger()->info('color、rdf、switch_page_theme 均未启用，无需处理。');
    }

    // standard profile 在 D11 弃用，切换到 minimal。
    // uninstall() 在 profile 仍为 standard 时可能遗留 color/rdf 的 module
    // 条目（见 update_to_d11 README「待删除字段脏数据」同一类 config 残留），
    // 这里显式清除卸载模块的条目，并把弃用的 standard 标记换成 minimal。
    $extension = $this->configFactory->getEditable('core.extension');
    $modules = $extension->get('module') ?? [];
    foreach (self::UNINSTALL as $module) {
      unset($modules[$module]);
    }
    unset($modules['standard']);
    if ($extension->get('profile') === 'standard') {
      $extension->set('profile', 'minimal');
      $this->logger()->success('安装 profile：standard → minimal。');
    }
    else {
      $this->logger()->info(sprintf('当前 profile 为 %s，无需切换。', $extension->get('profile')));
    }
    $modules['minimal'] = 1000;
    $extension->set('module', $modules)->save();
    $this->logger()->info('后续步骤：容器内执行 composer update drupal/color drupal/rdf drupal/switch_page_theme 移除 vendor 包。');
  }

}
