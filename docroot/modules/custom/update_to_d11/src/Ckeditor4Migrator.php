<?php

declare(strict_types=1);

namespace Drupal\update_to_d11;

use Drupal\ckeditor5\HTMLRestrictions;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\editor\EditorInterface;
use Drupal\filter\FilterFormatInterface;

/**
 * CKEditor 4 → CKEditor 5 迁移。
 *
 * 核心按钮由 SmartDefaultSettings 自动映射；本类补充 contrib 按钮的
 * 手工映射（FontSize/codeBlock/fullscreen）并处理 filter_html 兼容。
 */
class Ckeditor4Migrator {

  /**
   * 无 CKEditor 5 等价物的按钮（内容不受影响，仅按钮消失）。
   */
  protected const DROPPED_BUTTONS = [
    // textindent：无等价插件；full_html 未启用 filter_html，
    // 存量内联样式 text-indent 得以保留。
    'textindent',
    // ImceImage：imce 模块未安装，本就是死按钮。
    'ImceImage',
    // Maximize：ckeditor5_plugin_pack_fullscreen 子模块仅 1.5.x 提供，
    // 当前 1.2.0 无该子模块，丢弃全屏按钮。
    'Maximize',
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * 迁移全部 CKEditor 4 文本编辑器到 CKEditor 5。
   *
   * @return array
   *   报告：editors（逐编辑器日志）/ dropped / errors。
   */
  public function migrate(): array {
    $report = [
      'editors' => [],
      'dropped' => [],
      'errors' => [],
    ];

    // 1. 安装 CKEditor 5 与补充插件（fontSize、fullscreen）。
    $install = [
      'ckeditor5',
      'ckeditor5_plugin_pack',
      'ckeditor5_plugin_pack_font',
    ];
    $missing = array_filter(
      $install,
      static fn(string $m): bool => !\Drupal::moduleHandler()->moduleExists($m),
    );
    if ($missing) {
      \Drupal::service('module_installer')->install(array_values($missing));
      $report['editors'][] = '已安装模块：' . implode(', ', $missing);
    }

    // 2. 逐个转换 editor 实体。
    $storage = $this->entityTypeManager->getStorage('editor');
    /** @var \Drupal\editor\EditorInterface $editor */
    foreach ($storage->loadMultiple() as $editor) {
      if ($editor->getEditor() !== 'ckeditor') {
        continue;
      }
      try {
        $report['editors'][] = $this->migrateEditor($editor);
      }
      catch (\Exception $e) {
        $report['errors'][] = sprintf('editor %s: %s', $editor->id(), $e->getMessage());
      }
    }
    if (count($report['editors']) === 0) {
      $report['editors'][] = '未发现 CKEditor 4 编辑器，无需迁移。';
    }
    return $report;
  }

  /**
   * 转换单个编辑器配置。
   *
   * @return string
   *   执行日志。
   */
  protected function migrateEditor(EditorInterface $editor): string {
    /** @var \Drupal\filter\FilterFormatInterface $format */
    $format = $this->entityTypeManager->getStorage('filter_format')->load($editor->id());
    if (!$format) {
      throw new \RuntimeException('找不到对应的文本格式。');
    }

    // 收集旧 toolbar 按钮。
    $old_buttons = [];
    foreach ($editor->getSettings()['toolbar']['rows'] ?? [] as $row) {
      foreach ((array) $row as $group) {
        foreach ((array) ($group['items'] ?? []) as $button) {
          $old_buttons[] = $button;
        }
      }
    }

    // 核心迁移：映射按钮、启用插件、处理 source editing 增补。
    $smart = \Drupal::service('ckeditor5.smart_default_settings');
    [$new_editor] = $smart->computeSmartDefaultSettings($editor, $format);
    $settings = $new_editor->getSettings();

    // fontSize（plugin_pack font）输出 <span class>，
    // 启用 filter_html 的格式需放行 class 属性。
    $span_class_needed = in_array('FontSize', $old_buttons, TRUE)
      && $format->filters('filter_html')->status;
    if ($span_class_needed) {
      $this->allowSpanClass($format);
    }

    $editor->setEditor('ckeditor5');
    $editor->setSettings($settings);
    $editor->save();

    $dropped = array_values(array_intersect(self::DROPPED_BUTTONS, $old_buttons));
    $log = sprintf('%s：已切换到 CKEditor 5', $editor->id());
    if ($dropped) {
      $log .= '，丢弃按钮（' . implode('、', $dropped) . '）';
    }
    return $log . '。';
  }

  /**
   * 把 <span class> 合并进 filter_html 的 allowed_html。
   */
  protected function allowSpanClass(FilterFormatInterface $format): void {
    $filters = $format->get('filters');
    $allowed_html = (string) ($filters['filter_html']['settings']['allowed_html'] ?? '');
    $merged = HTMLRestrictions::fromString($allowed_html)
      ->merge(new HTMLRestrictions(['span' => ['class' => TRUE]]));
    $new_html = $merged->toFilterHtmlAllowedTagsString();
    if ($new_html === $allowed_html) {
      return;
    }
    $filters['filter_html']['settings']['allowed_html'] = $new_html;
    $format->set('filters', $filters);
    $format->save();
  }

  /**
   * 清理：卸载 CKEditor 4 及其 contrib 按钮模块（不可逆）。
   *
   * @return string[]
   *   执行日志。
   */
  public function cleanup(): array {
    $log = [];
    $uninstall = ['ckeditor', 'ckeditor_font', 'codesnippet', 'ckeditor_textindent'];
    $enabled = array_filter(
      $uninstall,
      static fn(string $m): bool => \Drupal::moduleHandler()->moduleExists($m),
    );
    if ($enabled) {
      \Drupal::service('module_installer')->uninstall(array_values($enabled));
      $log[] = '已卸载模块：' . implode(', ', $enabled);
    }
    else {
      $log[] = 'CKEditor 4 相关模块均已卸载。';
    }
    $log[] = '后续步骤：容器内执行 composer update drupal/ckeditor drupal/ckeditor_font drupal/codesnippet drupal/ckeditor_textindent 移除 vendor 包。';
    return $log;
  }

}
