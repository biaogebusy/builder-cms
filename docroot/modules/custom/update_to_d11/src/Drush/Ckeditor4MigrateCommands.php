<?php

declare(strict_types=1);

namespace Drupal\update_to_d11\Drush;

use Drupal\update_to_d11\Ckeditor4Migrator;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * CKEditor 4 → CKEditor 5 迁移命令。
 */
class Ckeditor4MigrateCommands extends DrushCommands {

  public function __construct(protected Ckeditor4Migrator $migrator) {
    parent::__construct();
  }

  #[CLI\Command(name: 'update-to-d11:ckeditor4-migrate', description: '迁移 CKEditor 4 编辑器配置到 CKEditor 5（SmartDefaultSettings + 补充 fontSize/codeBlock/fullscreen）。')]
  #[CLI\Usage(name: 'drush update-to-d11:ckeditor4-migrate', description: '执行迁移并输出逐编辑器报告。')]
  public function migrate(): void {
    if (!$this->io()->confirm('开始迁移 CKEditor 4 到 CKEditor 5？')) {
      return;
    }
    $report = $this->migrator->migrate();
    $this->io()->section('迁移报告');
    $this->io()->listing($report['editors']);
    if ($report['errors']) {
      $this->io()->error('失败明细：');
      $this->io()->listing($report['errors']);
      throw new \Exception('迁移存在失败项，请处理后重跑。');
    }
    $this->logger()->success('迁移完成，请到各文本格式的编辑器配置页人工复核 toolbar 与插件设置。');
  }

  #[CLI\Command(name: 'update-to-d11:ckeditor4-cleanup', description: '清理 CKEditor 4：卸载 ckeditor/ckeditor_font/codesnippet/ckeditor_textindent 模块。')]
  #[CLI\Usage(name: 'drush update-to-d11:ckeditor4-cleanup', description: '执行前请先 drush sql-dump 备份。')]
  public function cleanup(): void {
    if (!$this->io()->confirm('将卸载 ckeditor、ckeditor_font、codesnippet、ckeditor_textindent 模块，继续？', FALSE)) {
      $this->logger()->warning('已取消。');
      return;
    }
    $this->io()->section('清理日志');
    $this->io()->listing($this->migrator->cleanup());
    $this->logger()->success('清理完成。');
  }

}
