<?php

declare(strict_types=1);

namespace Drupal\update_to_d11;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\NodeInterface;

/**
 * Panelizer 字段数据迁移到 Layout Builder（layout_builder__layout）。
 */
class PanelizerMigrator {

  /**
   * 迁移处理的节点分批大小。
   */
  protected const BATCH_SIZE = 50;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * 获取有 panelizer 字段实例的 node bundle 列表。
   *
   * @return string[]
   */
  public function getPanelizerBundles(): array {
    $bundles = [];
    foreach ($this->configFactory->listAll('field.field.node.') as $name) {
      $config = $this->configFactory->get($name);
      if ($config->get('field_name') === 'panelizer' && $config->get('entity_type') === 'node') {
        $bundles[] = $config->get('bundle');
      }
    }
    return $bundles;
  }

  /**
   * 迁移 panelizer 字段数据到 layout_builder__layout。
   *
   * @return array
   *   统计报告：processed / skipped / failed / errors。
   */
  public function migrate(): array {
    $stats = [
      'bundles' => [],
      'processed' => 0,
      'skipped' => 0,
      'failed' => 0,
      'errors' => [],
      'display_log' => [],
    ];

    // 1. display 级迁移：把视图显示 panelizer 变体中的 views 块转换到
    // {类型}.{bundle}.json 视图显示的 Layout Builder sections
    // （NodeJson/TermJson/UserJson 的 renderLayoutBuilder() 回退路径消费），
    // 并剥离全部 panelizer 渲染设置。
    $stats['display_log'] = $this->migrateDisplays();

    $bundles = $this->getPanelizerBundles();

    // 2. 为每个 bundle 的 default 视图显示启用 Layout Builder（自动创建
    // layout_builder__layout 字段）。
    foreach ($bundles as $bundle) {
      $stats['bundles'][] = $bundle;
      $display = $this->entityTypeManager->getStorage('entity_view_display')->load("node.$bundle.default");
      if ($display && !$display->isLayoutBuilderEnabled()) {
        $display->enableLayoutBuilder();
        $display->save();
      }
    }
    if ($bundles) {
      $this->entityFieldManager->clearCachedFieldDefinitions();
    }

    // 3. 分批迁移节点（每个翻译独立持有自己的布局）。
    $storage = $this->entityTypeManager->getStorage('node');
    foreach ($bundles as $bundle) {
      $ids = $storage->getQuery()
        ->condition('type', $bundle)
        ->accessCheck(FALSE)
        ->execute();
      foreach (array_chunk($ids, self::BATCH_SIZE) as $chunk) {
        foreach ($storage->loadMultiple($chunk) as $node) {
          try {
            $result = $this->migrateNode($node);
            $stats[$result ? 'processed' : 'skipped']++;
          }
          catch (\Exception $e) {
            $stats['failed']++;
            $stats['errors'][] = sprintf('node %s (%s): %s', $node->id(), $bundle, $e->getMessage());
          }
        }
      }
    }
    return $stats;
  }

  /**
   * display 级迁移：panelizer 视图显示变体 → .json 视图显示的 LB sections。
   *
   * JSON 输出只消费 views 块（renderLayoutBuilder()），entity_field 块由
   * 视图显示自身的字段配置渲染，无需转换。
   *
   * @return string[]
   *   执行日志。
   */
  protected function migrateDisplays(): array {
    $log = [];
    $storage = $this->entityTypeManager->getStorage('entity_view_display');
    foreach ($this->configFactory->listAll('core.entity_view_display.') as $name) {
      $config = $this->configFactory->get($name);
      $panelizer = $config->get('third_party_settings.panelizer');
      if (!$panelizer) {
        continue;
      }
      $id = $config->get('id');
      [$entity_type_id, $bundle, $mode] = explode('.', $id);

      // 收集全部变体中的 views 块（按 weight 排序）。
      $views_blocks = [];
      foreach ((array) ($panelizer['displays'] ?? []) as $variant) {
        foreach ((array) ($variant['blocks'] ?? []) as $block) {
          if (($block['provider'] ?? '') === 'views' && str_starts_with((string) ($block['id'] ?? ''), 'views_block:')) {
            $views_blocks[] = $block;
          }
        }
      }
      uasort($views_blocks, static fn(array $a, array $b): int => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));

      if ($views_blocks) {
        $this->ensureJsonDisplay($entity_type_id, $bundle, $views_blocks);
        $log[] = sprintf('%s：%d 个 views 块已迁移到 %s.%s.json', $id, count($views_blocks), $entity_type_id, $bundle);
      }

      // 剥离源视图显示的 panelizer 设置。
      $display = $storage->load($id);
      if ($display) {
        foreach (array_keys((array) $display->getThirdPartySettings('panelizer')) as $key) {
          $display->unsetThirdPartySetting('panelizer', $key);
        }
        $display->save();
        $log[] = "{$id}：已剥离 panelizer 设置";
      }
    }
    return $log;
  }

  /**
   * 确保存在启用了 Layout Builder 的 {类型}.{bundle}.json 视图显示，
   * 并把 views 块写入其 sections。
   *
   * @param string $entity_type_id
   *   实体类型 ID（如 node、taxonomy_term）。
   * @param string $bundle
   *   bundle ID。
   * @param array $views_blocks
   *   有序的 views 块配置列表。
   */
  protected function ensureJsonDisplay(string $entity_type_id, string $bundle, array $views_blocks): void {
    // 确保 json view mode 存在。
    $mode_storage = $this->entityTypeManager->getStorage('entity_view_mode');
    $mode_id = $entity_type_id . '.json';
    if (!$mode_storage->load($mode_id)) {
      $mode_storage->create([
        'id' => $mode_id,
        'targetEntityType' => $entity_type_id,
        'label' => 'Json',
        'status' => TRUE,
      ])->save();
    }

    $storage = $this->entityTypeManager->getStorage('entity_view_display');
    $display_id = "{$entity_type_id}.{$bundle}.json";
    $display = $storage->load($display_id);
    if (!$display) {
      $display = $storage->create([
        'targetEntityType' => $entity_type_id,
        'bundle' => $bundle,
        'mode' => 'json',
        'status' => FALSE,
      ]);
    }
    if (!$display->isLayoutBuilderEnabled()) {
      $display->enableLayoutBuilder();
    }
    $section = new Section('layout_onecol');
    foreach (array_values($views_blocks) as $weight => $block) {
      $configuration = [
        'id' => $block['id'],
        'provider' => 'views',
        'label' => $block['label'] ?? '',
        'label_display' => $block['label_display'] ?? 0,
        'views_label' => $block['views_label'] ?? ($block['label'] ?? ''),
        'items_per_page' => $block['items_per_page'] ?? 'none',
      ];
      $section->appendComponent(new SectionComponent(
        Uuid::generate(),
        'content',
        $configuration,
        $weight,
      ));
    }
    $display->setThirdPartySetting('layout_builder', 'sections', [$section->toArray()]);
    $display->save();
  }

  /**
   * 迁移单个节点（含全部翻译）。
   *
   * @return bool
   *   是否写入了 layout_builder__layout。
   */
  protected function migrateNode(NodeInterface $node): bool {
    $changed = FALSE;
    foreach (array_keys($node->getTranslationLanguages()) as $langcode) {
      $translation = $node->getTranslation($langcode);
      $section = $this->buildSectionFromPanelizer($translation);
      if ($section) {
        $translation->set('layout_builder__layout', [$section]);
        $changed = TRUE;
      }
    }
    if ($changed) {
      $node->setNewRevision(FALSE);
      $node->save();
    }
    return $changed;
  }

  /**
   * 把 panelizer 字段 delta 0 的 panels_display 转换为一个 Layout Builder Section。
   */
  protected function buildSectionFromPanelizer(NodeInterface $translation): ?Section {
    $value = $translation->get('panelizer')->getValue();
    $blocks = $value[0]['panels_display']['blocks'] ?? [];
    $blocks = array_filter($blocks, static function (array $block): bool {
      if (($block['provider'] ?? '') === 'block_content') {
        return TRUE;
      }
      // 部分旧数据未写 provider，按插件 id 前缀识别。
      return str_starts_with($block['id'] ?? '', 'block_content:');
    });
    if (empty($blocks)) {
      return NULL;
    }
    uasort($blocks, static fn(array $a, array $b): int => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));

    $section = new Section('layout_onecol');
    foreach ($blocks as $block) {
      $block_uuid = explode(':', $block['id'] ?? '')[1] ?? '';
      if (empty($block_uuid)) {
        continue;
      }
      $configuration = [
        'id' => 'block_content:' . $block_uuid,
        'provider' => 'block_content',
        'label' => $block['label'] ?? '',
        'label_display' => FALSE,
      ];
      // 保留块 revision id，NodeJson 优先按 revision 加载。
      if (!empty($block['vid'])) {
        $configuration['vid'] = $block['vid'];
      }
      $section->appendComponent(new SectionComponent(
        Uuid::generate(),
        'content',
        $configuration,
        (int) ($block['weight'] ?? 0),
      ));
    }
    return $section->getComponents() ? $section : NULL;
  }

  /**
   * 验证迁移结果：逐翻译比对 panelizer 块 uuid 序列与 LB 组件 uuid 序列。
   *
   * @return array
   *   报告：ok / mismatch（含差异明细）/ missing（无 LB 布局的节点）。
   */
  public function verify(): array {
    $report = [
      'checked' => 0,
      'ok' => 0,
      'mismatch' => [],
      'missing' => [],
      'display_residue' => [],
    ];
    // display 级检查：不应残留任何 panelizer 设置。
    foreach ($this->configFactory->listAll('core.entity_view_display.') as $name) {
      $config = $this->configFactory->get($name);
      if ($config->get('third_party_settings.panelizer')) {
        $report['display_residue'][] = $name;
      }
    }
    $storage = $this->entityTypeManager->getStorage('node');
    foreach ($this->getPanelizerBundles() as $bundle) {
      $ids = $storage->getQuery()
        ->condition('type', $bundle)
        ->accessCheck(FALSE)
        ->execute();
      foreach ($storage->loadMultiple($ids) as $node) {
        foreach (array_keys($node->getTranslationLanguages()) as $langcode) {
          $translation = $node->getTranslation($langcode);
          $expected = $this->getPanelizerBlockUuids($translation);
          if (empty($expected)) {
            // panelizer 无数据，无需校验。
            continue;
          }
          $report['checked']++;
          $actual = $this->getLayoutBlockUuids($translation);
          if (empty($actual)) {
            $report['missing'][] = sprintf('node %s (%s / %s)', $node->id(), $bundle, $langcode);
          }
          elseif ($expected !== $actual) {
            $report['mismatch'][] = sprintf(
              'node %s (%s / %s): panelizer=%s layout=%s',
              $node->id(),
              $bundle,
              $langcode,
              implode(',', $expected),
              implode(',', $actual),
            );
          }
          else {
            $report['ok']++;
          }
        }
      }
    }
    return $report;
  }

  /**
   * 读取 panelizer 字段中有序的 block_content uuid 序列。
   *
   * @return string[]
   */
  protected function getPanelizerBlockUuids(NodeInterface $translation): array {
    $value = $translation->get('panelizer')->getValue();
    $blocks = $value[0]['panels_display']['blocks'] ?? [];
    $blocks = array_filter($blocks, static function (array $block): bool {
      return ($block['provider'] ?? '') === 'block_content'
        || str_starts_with($block['id'] ?? '', 'block_content:');
    });
    uasort($blocks, static fn(array $a, array $b): int => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));
    $uuids = [];
    foreach ($blocks as $block) {
      $uuids[] = explode(':', $block['id'])[1] ?? '';
    }
    return array_values(array_filter($uuids));
  }

  /**
   * 读取 layout_builder__layout 中有序的 block_content uuid 序列。
   *
   * @return string[]
   */
  protected function getLayoutBlockUuids(NodeInterface $translation): array {
    if ($translation->get('layout_builder__layout')->isEmpty()) {
      return [];
    }
    $uuids = [];
    foreach ($translation->get('layout_builder__layout')->getSections() as $section) {
      $components = $section->getComponents();
      uasort($components, static fn($a, $b): int => $a->getWeight() <=> $b->getWeight());
      foreach ($components as $component) {
        $id = $component->getConfiguration()['id'] ?? '';
        if (str_starts_with($id, 'block_content:')) {
          $uuids[] = substr($id, strlen('block_content:'));
        }
      }
    }
    return $uuids;
  }

  /**
   * 清理 panelizer 全部痕迹（不可逆，执行前务必备份数据库）。
   *
   * @return string[]
   *   执行日志。
   */
  public function cleanup(): array {
    $log = [];

    // 1. 剥离所有视图显示中的 panelizer 设置。
    foreach (['core.entity_view_display.', 'core.entity_form_display.'] as $prefix) {
      foreach ($this->configFactory->listAll($prefix) as $name) {
        $config = $this->configFactory->getEditable($name);
        if (!$config->get('third_party_settings.panelizer')) {
          continue;
        }
        $config->clear('third_party_settings.panelizer');
        $modules = $config->get('dependencies.module') ?? [];
        $modules = array_values(array_diff($modules, ['panelizer']));
        if ($modules) {
          $config->set('dependencies.module', $modules);
        }
        else {
          $config->clear('dependencies.module');
        }
        $config->save();
        $log[] = "已剥离 panelizer 设置：{$name}";
      }
    }

    // 2. 删除 panelizer 字段实例与存储（数据随存储删除）。
    foreach ($this->configFactory->listAll('field.field.') as $name) {
      $config = $this->configFactory->get($name);
      if ($config->get('field_name') === 'panelizer' && $config->get('entity_type') === 'node') {
        $id = sprintf('%s.%s.%s', $config->get('entity_type'), $config->get('bundle'), 'panelizer');
        if ($field = $this->entityTypeManager->getStorage('field_config')->load($id)) {
          $field->delete();
          $log[] = "已删除字段实例：{$id}";
        }
      }
    }
    if ($storage = FieldStorageConfig::loadByName('node', 'panelizer')) {
      $storage->delete();
      field_purge_batch(1000);
      $log[] = '已删除字段存储：node.panelizer';
    }

    // 3. 删除 ECK panelizer_display_attribute 实体类型全套配置。
    $log = array_merge($log, $this->cleanupEckEntityType());

    // 4. 卸载模块（ctools 保留，pathauto 等仍依赖）。
    $installer = \Drupal::service('module_installer');
    $uninstall = ['panelizer', 'panels_ipe', 'panels', 'ctools_block'];
    $enabled = array_filter($uninstall, static fn($m): bool => \Drupal::moduleHandler()->moduleExists($m));
    if ($enabled) {
      $installer->uninstall($enabled);
      $log[] = '已卸载模块：' . implode(', ', $enabled);
    }
    $log[] = '后续步骤：容器内执行 composer update drupal/panelizer drupal/panels drupal/panels_ipe 移除 vendor 包。';
    return $log;
  }

  /**
   * 删除 ECK panelizer_display_attribute 实体类型及其全部配置。
   *
   * @return string[]
   */
  protected function cleanupEckEntityType(): array {
    $log = [];
    $entity_type_id = 'panelizer_display_attribute';

    // 先删视图与显示、语言配置。
    $prefixes = [
      'views.view.manage_panelizer_display_attribute',
      'core.entity_form_display.' . $entity_type_id . '.',
      'core.entity_view_display.' . $entity_type_id . '.',
      'core.base_field_override.' . $entity_type_id . '.',
      'language.content_settings.' . $entity_type_id . '.',
    ];
    foreach ($prefixes as $prefix) {
      foreach ($this->configFactory->listAll($prefix) as $name) {
        $this->configFactory->getEditable($name)->delete();
        $log[] = "已删除配置：{$name}";
      }
    }

    // 删除字段实例与存储（含数据清理）。
    foreach ($this->configFactory->listAll('field.field.' . $entity_type_id . '.') as $name) {
      $config = $this->configFactory->get($name);
      $id = sprintf('%s.%s.%s', $config->get('entity_type'), $config->get('bundle'), $config->get('field_name'));
      if ($field = $this->entityTypeManager->getStorage('field_config')->load($id)) {
        $field->delete();
        $log[] = "已删除字段实例：{$id}";
      }
    }
    foreach ($this->configFactory->listAll('field.storage.' . $entity_type_id . '.') as $name) {
      $config = $this->configFactory->get($name);
      if ($storage = FieldStorageConfig::loadByName($entity_type_id, $config->get('field_name'))) {
        $storage->delete();
        $log[] = "已删除字段存储：{$name}";
      }
    }
    field_purge_batch(1000);

    // 删除 bundle 与实体类型（实体类型删除会级联删除数据表与引用字段）。
    foreach ($this->configFactory->listAll('eck.eck_type.' . $entity_type_id . '.') as $name) {
      $this->configFactory->getEditable($name)->delete();
      $log[] = "已删除配置：{$name}";
    }
    $name = 'eck.eck_entity_type.' . $entity_type_id;
    if ($this->configFactory->get($name)) {
      $this->configFactory->getEditable($name)->delete();
      $log[] = "已删除配置：{$name}（数据表随之删除）";
    }
    return $log;
  }

}
