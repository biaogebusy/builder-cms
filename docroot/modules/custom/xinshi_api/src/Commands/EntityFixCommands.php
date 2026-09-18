<?php

namespace Drupal\xinshi_api\Commands;

use Drupal\Core\Entity\EntityTypeInterface;
use Drush\Commands\DrushCommands;

/**
 * Drush 命令：实体定义修复工具。
 */
class EntityFixCommands extends DrushCommands {

  /**
   * 修复实体/字段定义不匹配的问题。
   *
   * @command xinshi:entity-fix
   * @aliases xef, entity-fix
   * @usage drush xinshi:entity-fix
   *   扫描并修复所有实体定义不匹配的问题。
   * @usage drush xef --dry-run
   *   仅检查，不执行修复（干运行）。
   *
   * @option dry-run 仅检测并显示问题，不执行实际修复。
   */
  public function entityFix(array $options = ['dry-run' => FALSE]) {
    $dry_run = (bool) $options['dry-run'];

    /** @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $repo */
    $repo = \Drupal::service('entity.last_installed_schema.repository');
    /** @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface $update_manager */
    $update_manager = \Drupal::entityDefinitionUpdateManager();

    // ── 1. 检查变更列表 ──
    $change_list = $update_manager->getChangeList();

    if (empty($change_list)) {
      $this->output()->writeln('<info>✅ 所有实体定义完全一致，无需修复。</info>');
      return;
    }

    $this->output()->writeln('<info>发现 ' . count($change_list) . ' 个实体定义不匹配，正在分析……</info>');
    $this->output()->writeln('');

    // ── 2. 遍历变更列表，分类处理 ──
    $fixed = [];
    $skipped = [];

    foreach ($change_list as $entity_type_id => $changes) {
      $this->output()->writeln("── 实体类型: <comment>{$entity_type_id}</comment> ──");

      // 2a. 实体类型本身有变更
      if (!empty($changes['entity_type'])) {
        $code_def = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
        $last_def = $repo->getLastInstalledDefinition($entity_type_id);

        if ($last_def === NULL) {
          $reason = '上次安装定义不存在（新增实体类型未记录）';
        } else {
          $reason = '实体类型定义属性已变更';

          // 比对差异
          $diffs = $this->compareEntityTypeDefinitions($last_def, $code_def);
          foreach ($diffs as $key => $diff) {
            $this->output()->writeln("  差异 - <comment>{$key}</comment>:");
            $this->output()->writeln("    代码:  <comment>" . json_encode($diff['code'], JSON_UNESCAPED_UNICODE) . "</comment>");
            $this->output()->writeln("    已安装: <comment>" . json_encode($diff['installed'], JSON_UNESCAPED_UNICODE) . "</comment>");
          }
        }

        $this->output()->writeln("  原因: {$reason}");

        if ($dry_run) {
          $skipped[] = $entity_type_id . ' (entity_type)';
          $this->output()->writeln("  <comment>[DRY-RUN] 将跳过修复。</comment>");
        } else {
          try {
            $repo->setLastInstalledDefinition($code_def);
            $fixed[] = $entity_type_id . ' (entity_type)';
            $this->output()->writeln("  <info>✅ 已修复: 写入已安装定义。</info>");
          } catch (\Exception $e) {
            $skipped[] = $entity_type_id . " (entity_type, 失败: {$e->getMessage()})";
            $this->output()->writeln("  <error>❌ 修复失败: {$e->getMessage()}</error>");
          }
        }
      }

      // 2b. 字段存储定义有变更
      if (!empty($changes['field_storage_definitions'])) {
        foreach ($changes['field_storage_definitions'] as $field_name) {
          $this->output()->writeln("  字段: <comment>{$field_name}</comment> (存储定义需要更新)");

          if ($dry_run) {
            $skipped[] = "{$entity_type_id}.{$field_name} (field)";
            $this->output()->writeln("    <comment>[DRY-RUN] 将跳过修复。</comment>");
          } else {
            try {
              $update_manager->updateFieldableEntityType(
                \Drupal::entityTypeManager()->getDefinition($entity_type_id),
                $repo->getLastInstalledFieldStorageDefinitions($entity_type_id)
              );
              $fixed[] = "{$entity_type_id}.{$field_name} (field)";
              $this->output()->writeln("    <info>✅ 已修复: 更新字段存储定义。</info>");
            } catch (\Exception $e) {
              $skipped[] = "{$entity_type_id}.{$field_name} (field, 失败: {$e->getMessage()})";
              $this->output()->writeln("    <error>❌ 修复失败: {$e->getMessage()}</error>");
            }
          }
        }
      }
    }

    // ── 3. 汇总 ──
    $this->output()->writeln('');
    $this->output()->writeln('<info>──────────────────────────</info>');

    if ($dry_run) {
      $this->output()->writeln("<comment>[DRY-RUN 模式] 未执行任何更改。</comment>");
    }

    $this->output()->writeln("已修复: <info>" . count($fixed) . "</info> 项");
    foreach ($fixed as $item) {
      $this->output()->writeln("  ✅ {$item}");
    }

    $this->output()->writeln("已跳过: <comment>" . count($skipped) . "</comment> 项");
    foreach ($skipped as $item) {
      $this->output()->writeln("  ⏭️  {$item}");
    }

    if (!$dry_run && count($fixed) > 0) {
      $this->output()->writeln('');
      $this->output()->writeln('<info>🔁 建议运行 drush cr 以确保状态报告刷新。</info>');
    }
  }

  /**
   * 比较两个实体类型定义的关键属性差异。
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $old
   * @param \Drupal\Core\Entity\EntityTypeInterface $new
   *
   * @return array
   */
  private function compareEntityTypeDefinitions(EntityTypeInterface $old, EntityTypeInterface $new): array {
    $diffs = [];
    // 需要比较的关键属性白名单
    $keys = [
      'class', 'provider', 'id', 'label', 'group', 'group_label',
      'admin_permission', 'collection_permission',
      'handlers', 'links',
      'translatable', 'revisionable',
      'field_ui_base_route',
      'constraints',
    ];

    foreach ($keys as $key) {
      $old_val = $this->safeGet($old, $key);
      $new_val = $this->safeGet($new, $key);
      if (json_encode($old_val) !== json_encode($new_val)) {
        $diffs[$key] = [
          'installed' => $old_val,
          'code' => $new_val,
        ];
      }
    }

    return $diffs;
  }

  /**
   * 安全获取实体类型定义属性值。
   */
  private function safeGet(EntityTypeInterface $entity_type, string $key) {
    try {
      return $entity_type->get($key);
    } catch (\Exception $e) {
      return NULL;
    }
  }

}