<?php

declare(strict_types=1);

namespace Drupal\update_to_d11\Drush;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * seven 主题退役命令。
 *
 * seven 核心版随 Drupal 11 移除（backport 包 2.0 仅 beta）。后台已启用 gin，
 * 本命令把仍指向 seven 的 admin theme 切到 gin 后卸载 seven 主题。
 */
class SevenCleanupCommands extends DrushCommands {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ThemeHandlerInterface $themeHandler,
  ) {
    parent::__construct();
  }

  #[CLI\Command(name: 'update-to-d11:seven-cleanup', description: 'admin theme 若为 seven 则切到 gin，随后卸载 seven 主题。')]
  #[CLI\Usage(name: 'drush update-to-d11:seven-cleanup', description: '执行前请先 drush sql-dump 备份。')]
  public function cleanup(): void {
    if (!$this->io()->confirm('将把 admin theme 从 seven 切换到 gin（若适用）并卸载 seven 主题，继续？', FALSE)) {
      $this->logger()->warning('已取消。');
      return;
    }

    if (!$this->themeHandler->themeExists('gin')) {
      $this->themeHandler->install(['gin']);
      $this->logger()->info('gin 主题未安装，已安装。');
    }

    // admin theme 仍指向 seven 时切到 gin（卸载中的主题不能被引用）。
    $theme_config = $this->configFactory->getEditable('system.theme');
    foreach (['admin', 'default'] as $key) {
      if ($theme_config->get($key) === 'seven') {
        $theme_config->set($key, 'gin')->save();
        $this->logger()->info(sprintf('system.theme.%s：seven → gin', $key));
      }
    }

    if ($this->themeHandler->themeExists('seven')) {
      $this->themeHandler->uninstall(['seven']);
      $this->logger()->success('已卸载 seven 主题。');
    }
    else {
      // seven 文件已随 D11 代码部署消失时，卸载流程走不到，
      // 但 core.extension 的已安装列表仍会残留 seven（表现为"缺失主题"警告）。
      $extension_config = $this->configFactory->getEditable('core.extension');
      if ($extension_config->get('theme.seven') !== NULL) {
        $extension_config->clear('theme.seven')->save();
        $this->logger()->success('seven 主题文件已缺失，已从 core.extension 移除残留记录。');
      }
      else {
        $this->logger()->info('seven 主题未安装，无需处理。');
      }
    }
    $this->logger()->info('vendor 中的 drupal/seven 包已随 11.x 分支的 composer update 移除，无需再单独执行。');
  }

}
