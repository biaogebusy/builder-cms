<?php

declare(strict_types=1);

namespace Drupal\update_to_d11\Drush;

use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * 清理实体类型已不存在的待删除字段记录。
 *
 * 站点历史上启用过 workspaces（后禁用），其 base field `upstream` 被标记删除后
 * 残留在 field.storage.deleted / field.field.deleted 状态里。由于 workspace 实体
 * 类型已不存在，field_purge_batch() 遍历到它时会抛 "The workspace entity type
 * does not exist"，阻塞所有字段清理（含 panelizer 清理）。这里统一移除这类
 * 「实体类型已不存在的待删除字段」，避免生产环境字段清理被脏数据卡住。
 */
class StaleFieldCleanupCommands extends DrushCommands {

  #[CLI\Command(name: 'update-to-d11:stale-field-cleanup', description: '移除实体类型已不存在的待删除字段（如 workspace-upstream）。')]
  #[CLI\Usage(name: 'drush update-to-d11:stale-field-cleanup', description: '执行前请先 drush sql-dump 备份。')]
  public function cleanup(): void {
    if (!$this->io()->confirm('将移除实体类型已不存在的待删除字段（如 workspace-upstream），继续？', FALSE)) {
      $this->logger()->warning('已取消。');
      return;
    }

    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager */
    $entityTypeManager = \Drupal::entityTypeManager();
    /** @var \Drupal\Core\Field\DeletedFieldsRepositoryInterface $repository */
    $repository = \Drupal::service('entity_field.deleted_fields_repository');

    $removed = [];
    foreach ($repository->getFieldStorageDefinitions() as $storage) {
      $entityTypeId = $storage->getTargetEntityTypeId();
      if (!$entityTypeManager->hasDefinition($entityTypeId)) {
        $repository->removeFieldStorageDefinition($storage);
        $removed[] = sprintf('%s (storage, %s)', $storage->getUniqueStorageIdentifier(), $entityTypeId);
      }
    }
    foreach ($repository->getFieldDefinitions() as $field) {
      $entityTypeId = $field->getTargetEntityTypeId();
      if (!$entityTypeManager->hasDefinition($entityTypeId)) {
        $repository->removeFieldDefinition($field);
        $removed[] = sprintf('%s (field, %s)', $field->getUniqueIdentifier(), $entityTypeId);
      }
    }

    if ($removed) {
      $this->io()->section('已移除的待删除字段');
      $this->io()->listing($removed);
      $this->logger()->success('清理完成。');
    }
    else {
      $this->logger()->info('未发现实体类型已不存在的待删除字段，无需处理。');
    }
  }

}
