<?php

declare(strict_types=1);

namespace Drupal\update_to_d11;

/**
 * 模块文件已缺失时的强制卸载兜底。
 *
 * ModuleInstaller::uninstall() 对文件已从磁盘消失的模块会静默返回 FALSE
 * （extension.list.module 只认磁盘上存在的扩展），core.extension 里的残留
 * 记录会触发"缺失模块"状态警告。本类绕过文件系统直接清理配置与数据，
 * 等价于正常卸载流程中与模块文件无关的那部分清理动作。
 */
final class MissingModuleCleaner {

  /**
   * 强制移除一个文件已缺失的已安装模块。
   *
   * @param string $module
   *   模块机名（文件已消失、但仍记录在 core.extension 的模块）。
   * @param array $config_names
   *   该模块持有的顶层配置对象名（如 conflict.settings），随清理删除。
   * @param array $tables
   *   该模块 hook_schema() 创建的数据表，随清理删除。
   */
  public static function purge(string $module, array $config_names = [], array $tables = []): void {
    \Drupal::configFactory()->getEditable('core.extension')
      ->clear("module.$module")
      ->save();
    \Drupal::service('update.update_hook_registry')->deleteInstalledVersion($module);
    foreach ($config_names as $name) {
      \Drupal::configFactory()->getEditable($name)->delete();
    }
    $schema = \Drupal::database()->schema();
    foreach ($tables as $table) {
      if ($schema->tableExists($table)) {
        $schema->dropTable($table);
      }
    }
    \Drupal::service('extension.list.module')->reset();
  }

}
