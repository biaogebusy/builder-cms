<?php

declare(strict_types=1);

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Database\Connection;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;

/**
 * Restores missing landing-page layouts without saving or replacing revisions.
 */
final class PanelizerLayoutRecovery {

  private const TABLES = [
    'node__panelizer' => 'node__layout_builder__layout',
    'node_revision__panelizer' => 'node_revision__layout_builder__layout',
  ];

  private array $writes = [];
  private array $fingerprints = [];
  private array $blocks = [];

  public function __construct(private Connection $db, private ?int $nid = NULL) {}

  /**
   * Returns a read-only plan bound to the site, configuration and source data.
   */
  public function preview(): array {
    $this->writes = $this->fingerprints = $this->blocks = [];
    $report = [
      'status' => 'preview',
      'site_path' => \Drupal::service('kernel')->getSitePath(),
      'site_uuid' => \Drupal::config('system.site')->get('uuid'),
      'bundle' => 'landing_page', 'nid' => $this->nid, 'view_mode' => 'full',
      'restore' => [], 'existing' => [], 'empty' => [], 'pages' => [], 'blockers' => [],
    ];
    foreach (['layout_builder', 'layout_builder_at', 'block_content', 'xinshi_editor'] as $module) {
      if (!\Drupal::moduleHandler()->moduleExists($module)) {
        $report['blockers'][] = "Required module is not enabled: $module";
      }
    }
    foreach ([...array_keys(self::TABLES), ...array_values(self::TABLES)] as $table) {
      if (!$this->db->schema()->tableExists($table)) {
        $report['blockers'][] = "Required table is missing: $table";
      }
    }
    $config = \Drupal::service('config.storage');
    foreach (['system.site', 'core.extension', 'field.storage.node.layout_builder__layout',
      'field.field.node.landing_page.layout_builder__layout',
      'core.entity_view_display.node.landing_page.default',
      'core.entity_view_display.node.landing_page.full',
      'core.entity_view_display.node.landing_page.json'] as $name) {
      $this->fingerprints['config'][$name] = $config->read($name);
    }
    $field = $config->read('field.storage.node.layout_builder__layout');
    if (!$field || ($field['type'] ?? '') !== 'layout_section' || empty($field['translatable'])) {
      $report['blockers'][] = 'An installed, translatable Layout Builder field storage is required.';
    }
    $display = $this->fingerprints['config']['core.entity_view_display.node.landing_page.default'];
    if (!$display || empty($display['status'])) {
      $report['blockers'][] = 'The landing_page default display must exist and be enabled.';
    }
    if ($report['blockers']) {
      return $report;
    }

    $current = [];
    foreach (self::TABLES as $source => $target) {
      $report['restore'][$target] = $report['existing'][$target] = $report['empty'][$source] = 0;
      $offset = 0;
      do {
        $query = $this->db->select($source, 'p')->fields('p');
        $query->join('node', 'n', 'n.nid = p.entity_id');
        $query->condition('n.type', 'landing_page')->condition('p.deleted', 0);
        if ($this->nid !== NULL) { $query->condition('p.entity_id', $this->nid); }
        foreach (['entity_id', 'revision_id', 'langcode', 'delta', 'panelizer_view_mode'] as $column) {
          $query->orderBy('p.' . $column);
        }
        $rows = $query->range($offset, 50)->execute()->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
          $key = implode(':', [$row['entity_id'], $row['revision_id'], $row['langcode']]);
          $context = "$source $key";
          $this->fingerprints['source'][$source][] = $row;
          if (($row['panelizer_view_mode'] ?? '') !== 'full') { continue; }
          try {
            if ($row['bundle'] !== 'landing_page') {
              throw new RuntimeException('Source bundle differs from the node bundle.');
            }
            if (isset($this->fingerprints['full'][$source][$key])) {
              throw new RuntimeException('Multiple full layouts exist for the same translation/revision.');
            }
            $this->fingerprints['full'][$source][$key] = $row['panelizer_panels_display'];
            $data_table = $source === 'node__panelizer' ? 'node_field_data' : 'node_field_revision';
            $node_data = $this->db->select($data_table, 'd')->fields('d')
              ->condition('nid', $row['entity_id'])->condition('vid', $row['revision_id'])
              ->condition('langcode', $row['langcode'])->execute()->fetchAssoc();
            if (!$node_data) { throw new RuntimeException('Matching node translation/revision is missing.'); }
            $this->fingerprints['nodes'][$data_table][$key] = $node_data;
            $blocks = $this->decode($row['panelizer_panels_display']);
            foreach ($blocks as $block) { $this->checkBlock($block); }
            if ($source === 'node__panelizer') { $current[$key] = $blocks; }
            elseif (isset($current[$key]) && $current[$key] !== $blocks) {
              throw new RuntimeException('Current and revision layouts disagree.');
            }
            $existing = $this->db->select($target, 'l')->fields('l')
              ->condition('entity_id', $row['entity_id'])->condition('langcode', $row['langcode'])
              ->condition('deleted', 0);
            if ($source !== 'node__panelizer') { $existing->condition('revision_id', $row['revision_id']); }
            $saved = $existing->orderBy('delta')->execute()->fetchAll(PDO::FETCH_ASSOC);
            $this->fingerprints['target'][$target][$key] = $saved;
            if ($source === 'node_revision__panelizer' && isset($current[$key])) {
              $live = $this->fingerprints['target']['node__layout_builder__layout'][$key] ?? [];
              if ((bool) $live !== (bool) $saved) {
                throw new RuntimeException('Current/revision target layouts are only partially present.');
              }
            }
            if ($saved) { $report['existing'][$target]++; }
            elseif (!$blocks) { $report['empty'][$source]++; }
            else {
              $this->writes[] = ['table' => $target, 'row' => $row, 'blocks' => $blocks];
              $report['restore'][$target]++;
            }
            if ($source === 'node__panelizer') {
              $report['pages'][] = ['nid' => $row['entity_id'], 'vid' => $row['revision_id'],
                'langcode' => $row['langcode'], 'blocks' => count($blocks),
                'action' => $saved ? 'preserve' : ($blocks ? 'restore' : 'empty')];
            }
          }
          catch (Throwable $error) { $report['blockers'][] = "$context: " . $error->getMessage(); }
        }
        $offset += count($rows);
      } while (count($rows) === 50);
    }
    // A current layout must also have its authoritative revision-table source.
    foreach ($current as $key => $blocks) {
      if (!isset($this->fingerprints['full']['node_revision__panelizer'][$key])) {
        $report['blockers'][] = "Current layout $key has no matching historical source.";
      }
    }
    if (!$current) { $report['blockers'][] = 'No current full landing-page layouts were found.'; }
    $this->fingerprints['blocks'] = $this->blocks;
    $report['configuration'] = [
      'default_display' => 'enable_layout_builder_and_overrides',
      'layout_field' => 'enable_translation',
    ];
    $report['confirmation_hash'] = hash('sha256', serialize([$report, $this->fingerprints]));
    return $report;
  }

  /**
   * Validates and converts the retained array format used by Panelizer.
   */
  private function decode(string $raw): array {
    $display = @unserialize($raw, ['allowed_classes' => FALSE]);
    if (!is_array($display) || !isset($display['blocks']) || !is_array($display['blocks'])) {
      throw new RuntimeException('The serialized display must contain a blocks array.');
    }
    $blocks = [];
    foreach ($display['blocks'] as $block) {
      $id = is_array($block) ? ($block['id'] ?? NULL) : NULL;
      if (!is_string($id) || !str_starts_with($id, 'block_content:') || !Uuid::isValid(substr($id, 14))) {
        throw new RuntimeException('Unsupported block plugin or invalid block UUID.');
      }
      if (isset($block['provider']) && $block['provider'] !== 'block_content') {
        throw new RuntimeException('Block provider differs from its plugin ID.');
      }
      $weight = $block['weight'] ?? 0;
      if (filter_var($weight, FILTER_VALIDATE_INT) === FALSE) {
        throw new RuntimeException('Block weight must be an integer.');
      }
      $configuration = ['id' => $id, 'provider' => 'block_content',
        'label' => is_string($block['label'] ?? NULL) ? $block['label'] : '', 'label_display' => FALSE];
      if (isset($block['vid']) && $block['vid'] !== '' && $block['vid'] !== NULL) {
        $vid = filter_var($block['vid'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($vid === FALSE) { throw new RuntimeException('Block revision ID must be positive.'); }
        $configuration['vid'] = $vid;
      }
      $blocks[] = ['configuration' => $configuration, 'weight' => (int) $weight];
    }
    usort($blocks, static fn(array $a, array $b): int => $a['weight'] <=> $b['weight']);
    return $blocks;
  }

  /**
   * Checks both existence and UUID ownership of a pinned block revision.
   */
  private function checkBlock(array $block): void {
    $config = $block['configuration'];
    $key = $config['id'] . ':' . ($config['vid'] ?? 'default');
    if (isset($this->blocks[$key])) { return; }
    $storage = \Drupal::entityTypeManager()->getStorage('block_content');
    $storage->resetCache();
    $entity = isset($config['vid']) ? $storage->loadRevision($config['vid'])
      : (array_values($storage->loadByProperties(['uuid' => substr($config['id'], 14)]))[0] ?? NULL);
    if (!$entity || $entity->uuid() !== substr($config['id'], 14)) {
      throw new RuntimeException("Missing block or mismatched revision: $key");
    }
    $this->blocks[$key] = hash('sha256', serialize($entity->toArray()));
  }

  /**
   * Applies only the reviewed plan in maintenance mode, with a database backup.
   */
  public function apply(string $siteUuid, string $confirmation, string $backup): array {
    if (!\Drupal::state()->get('system.maintenance_mode')) {
      throw new RuntimeException('Enable maintenance mode and stop writers before applying recovery.');
    }
    if (!is_file($backup) || !is_readable($backup) || filesize($backup) === 0) {
      throw new RuntimeException('Pass the readable, nonempty database backup path.');
    }
    $lock = \Drupal::service('lock.persistent');
    if (!$lock->acquire('panelizer-layout-recovery', 3600)) {
      throw new RuntimeException('Another layout recovery is running.');
    }
    $transaction = $this->db->startTransaction();
    try {
      $report = $this->preview();
      if ($report['blockers'] || $report['site_uuid'] !== $siteUuid ||
        !hash_equals($report['confirmation_hash'] ?? '', $confirmation)) {
        throw new RuntimeException('Site or preview differs, or blockers remain. Run preview again.');
      }
      $displays = \Drupal::entityTypeManager()->getStorage('entity_view_display');
      $display = $displays->load('node.landing_page.default');
      if (!$display->isOverridable()) { $display->setOverridable(TRUE)->save(); }
      $fields = \Drupal::entityTypeManager()->getStorage('field_config');
      $field = $fields->load('node.landing_page.layout_builder__layout');
      if (!$field) { throw new RuntimeException('Layout field creation failed.'); }
      if (!$field->isTranslatable()) { $field->setTranslatable(TRUE)->save(); }

      foreach ($this->writes as $write) {
        $components = [];
        foreach ($write['blocks'] as $block) {
          $components[] = (new SectionComponent(
            \Drupal::service('uuid')->generate(), 'content', $block['configuration'],
          ))->setWeight($block['weight']);
        }
        $section = new Section('layout_onecol', [], $components);
        $row = array_intersect_key($write['row'], array_flip([
          'bundle', 'deleted', 'entity_id', 'revision_id', 'langcode',
        ]));
        $row['delta'] = 0;
        $row['layout_builder__layout_section'] = serialize($section);
        // Direct field inserts preserve timestamps, moderation and the original revision IDs.
        $this->db->insert($write['table'])->fields($row)->execute();
      }
      \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
      // Field creation can leave the SQL storage's table mapping cached in this process.
      \Drupal::entityTypeManager()->clearCachedDefinitions();
      \Drupal::entityTypeManager()->getStorage('node')->resetCache();
      foreach ($this->writes as $write) {
        $row = $write['row'];
        $storage = \Drupal::entityTypeManager()->getStorage('node');
        $node = $write['table'] === 'node__layout_builder__layout'
          ? $storage->load($row['entity_id']) : $storage->loadRevision($row['revision_id']);
        $translation = $node->getTranslation($row['langcode']);
        $actual = [];
        foreach ($translation->get('layout_builder__layout')->getSections() as $section) {
          foreach ($section->getComponents() as $component) {
            $actual[] = ['configuration' => $component->toArray()['configuration'], 'weight' => $component->getWeight()];
          }
        }
        usort($actual, static fn(array $a, array $b): int => $a['weight'] <=> $b['weight']);
        if ($actual !== $write['blocks']) {
          throw new RuntimeException(sprintf(
            'Persisted layout differs for node %s revision %s / %s (expected %d blocks, read %d).',
            $row['entity_id'], $row['revision_id'], $row['langcode'], count($write['blocks']), count($actual),
          ));
        }
      }
      $tags = ['node_list'];
      foreach ($this->writes as $write) { $tags[] = 'node:' . $write['row']['entity_id']; }
      \Drupal::service('cache_tags.invalidator')->invalidateTags(array_unique($tags));
      unset($transaction);
      $report['status'] = 'applied';
      return $report;
    }
    catch (Throwable $error) {
      if (isset($transaction)) { $transaction->rollBack(); }
      throw $error;
    }
    finally { $lock->release('panelizer-layout-recovery'); }
  }

}
