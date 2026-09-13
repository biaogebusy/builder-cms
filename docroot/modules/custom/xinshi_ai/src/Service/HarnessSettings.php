<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

/** Shared defaults and the explicit allowlist for chat task configuration. */
final class HarnessSettings {

  public const MAX_LIMIT = 9007199254740991;

  public const DEFAULTS = [
    'tools' => ['pages_enabled' => FALSE, 'disabled' => []],
    'task_limits' => ['max_model_calls' => 40, 'max_tokens' => 200000],
  ];

  /** Only tools shipped by the Node application can be configured here. */
  public const TOOL_GROUPS = [
    'utility' => ['label' => '通用查询', 'tools' => [
      'query_time_date' => '日期与时间', 'get_weather' => '天气查询',
    ]],
    'cms' => ['label' => '内容查询', 'tools' => [
      'get_content' => '站点内容', 'get_node_type' => '内容类型',
      'get_conversations' => '对话记录', 'get_sessions' => '会话记录',
      'get_components' => '组件目录与模板', 'get_landing_page' => '页面列表',
      'get_nodes_statistics' => '内容统计', 'get_users_statistics' => '用户统计',
    ]],
    'design' => ['label' => '设计辅助', 'tools' => [
      'get_assets' => '设计素材', 'get_design_system' => '设计系统建议',
    ]],
    'pages' => ['label' => '页面草稿', 'tools' => [
      'create_page' => '创建草稿', 'get_page' => '读取本人草稿',
      'apply_widget' => '追加组件', 'delete_page' => '删除本人草稿',
    ]],
  ];

  public static function toolNames(): array {
    return array_keys(array_merge(...array_column(self::TOOL_GROUPS, 'tools')));
  }

  /** Missing settings on existing sites behave like a new installation. */
  public static function normalize(mixed $value): array {
    $result = self::DEFAULTS;
    if (!is_array($value)) {
      return $result;
    }
    $tools = $value['tools'] ?? NULL;
    if (is_array($tools) && is_bool($tools['pages_enabled'] ?? NULL)) {
      $result['tools']['pages_enabled'] = $tools['pages_enabled'];
    }
    if (is_array($tools) && is_array($tools['disabled'] ?? NULL)) {
      $result['tools']['disabled'] = array_values(array_intersect(self::toolNames(),
        array_filter($tools['disabled'], 'is_string')));
    }
    $limits = $value['task_limits'] ?? NULL;
    if (is_array($limits)) {
      foreach ($result['task_limits'] as $key => $default) {
        $limit = $limits[$key] ?? NULL;
        if (is_int($limit) && $limit > 0 && $limit <= self::MAX_LIMIT) {
          $result['task_limits'][$key] = $limit;
        }
      }
    }
    return $result;
  }

}
