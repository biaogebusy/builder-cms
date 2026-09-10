<?php

declare(strict_types=1);

namespace Drupal\update_to_d11\Drush;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\update_to_d11\MissingModuleCleaner;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * switch_page_theme 替换命令。
 *
 * switch_page_theme 4.x 不支持 D11。本命令把其路径规则迁移到
 * update_to_d11 内置的 ManageThemeNegotiator，随后卸载该模块。
 */
class SwitchPageThemeReplaceCommands extends DrushCommands {

  public function __construct(protected ConfigFactoryInterface $configFactory) {
    parent::__construct();
  }

  #[CLI\Command(name: 'update-to-d11:switch-page-theme-replace', description: '迁移 switch_page_theme 路径规则到 update_to_d11 主题协商器并卸载该模块。')]
  #[CLI\Usage(name: 'drush update-to-d11:switch-page-theme-replace', description: '执行前请先 drush sql-dump 备份。')]
  public function replace(): void {
    if (!$this->io()->confirm('将迁移 switch_page_theme 规则到 update_to_d11 并卸载该模块，继续？', FALSE)) {
      $this->logger()->warning('已取消。');
      return;
    }

    $spt_table = $this->configFactory->get('switch_page_theme.settings')->get('spt_table') ?? [];
    $rules = [];
    foreach ($spt_table as $value) {
      $rules[] = [
        'status' => (int) ($value['status'] ?? 0),
        'pages' => (string) ($value['pages'] ?? ''),
        'theme' => (string) ($value['theme'] ?? ''),
      ];
    }
    if ($rules) {
      // 先写入迁移后的规则，negotiator 立即可用，再卸载原模块（其配置随之删除）。
      $this->configFactory->getEditable('update_to_d11.settings')
        ->set('switch_page_theme_rules', $rules)
        ->save();

      $this->io()->section('迁移后的路径规则');
      $this->io()->listing(array_map(
        static fn(array $r): string => sprintf('%s → %s（%s）', str_replace("\r\n", ' ', $r['pages']), $r['theme'], $r['status'] ? '启用' : '停用'),
        $rules,
      ));
    }
    else {
      $this->logger()->warning('switch_page_theme.settings 无规则可迁移。');
    }

    $was_installed = \Drupal::moduleHandler()->moduleExists('switch_page_theme');
    if ($was_installed) {
      \Drupal::service('module_installer')->uninstall(['switch_page_theme']);
    }
    // 文件已随 11.x 部署消失时 uninstall() 会静默跳过，强制清理残留记录。
    if ($this->configFactory->getEditable('core.extension')->get('module.switch_page_theme') !== NULL) {
      MissingModuleCleaner::purge('switch_page_theme', ['switch_page_theme.settings']);
      $this->logger()->success('switch_page_theme 文件已缺失，已强制清理残留记录（含其 settings 配置）。');
    }
    elseif ($was_installed) {
      $this->logger()->success('已卸载 switch_page_theme 模块。');
    }
    else {
      $this->logger()->info('switch_page_theme 未启用，无需处理。');
    }
    $this->logger()->info('vendor 中的 drupal/switch_page_theme 包已随 11.x 分支的 composer update 移除，无需再单独执行。');
  }

}
