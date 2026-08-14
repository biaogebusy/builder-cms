<?php

namespace Drupal\xinshi_layout\Batch;

use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;

/**
 * Batch processing: converts translation Panelizer data to Layout Builder.
 */
class LayoutMigrationBatch {

  /**
   * Processes a single translation: Panelizer → Layout Builder.
   *
   * Reads the translation's OWN panelizer field data and converts
   * it to layout_builder__layout sections/components.
   *
   * @param int $nid
   *   The node ID.
   * @param string $target_langcode
   *   The target language code (the translation to migrate).
   * @param array $context
   *   Batch context.
   */
  public static function processItem($nid, $target_langcode, &$context) {
    if (!isset($context['results'])) {
      $context['results'] = [
        'synced' => [],
        'skipped' => [],
        'errors' => [],
      ];
    }

    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    /** @var \Drupal\node\NodeInterface|null $node */
    $node = $node_storage->load($nid);
    if (!$node) {
      $context['results']['errors'][] = t('Node @nid not found.', ['@nid' => $nid]);
      return;
    }

    // Get the translation entity.
    if (!$node->hasTranslation($target_langcode)) {
      $context['results']['skipped'][] = "$nid:$target_langcode (no translation)";
      return;
    }
    /** @var \Drupal\node\NodeInterface $trans */
    $trans = $node->getTranslation($target_langcode);

    // Skip if translation already has Layout Builder data.
    if (self::hasLayoutBuilderData($trans)) {
      $context['results']['skipped'][] = "$nid:$target_langcode (already has layout)";
      return;
    }

    // Read Panelizer data from the TRANSLATION (not source).
    if (!$trans->hasField('panelizer')) {
      $context['results']['skipped'][] = "$nid:$target_langcode (no panelizer field)";
      return;
    }
    $panelizer = $trans->get('panelizer')->getValue();
    $blocks_config = $panelizer[0]['panels_display']['blocks'] ?? [];
    if (empty($blocks_config)) {
      $context['results']['skipped'][] = "$nid:$target_langcode (no panelizer blocks)";
      return;
    }

    // Filter to block_content provider only.
    $blocks_config = array_filter($blocks_config, function ($block) {
      return ($block['provider'] ?? '') === 'block_content';
    });
    if (empty($blocks_config)) {
      $context['results']['skipped'][] = "$nid:$target_langcode (no block_content in panelizer)";
      return;
    }

    // Sort by weight to preserve order.
    usort($blocks_config, function ($a, $b) {
      return ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0);
    });

    $block_storage = \Drupal::entityTypeManager()->getStorage('block_content');

    // Build a single one-column section with all blocks.
    $section = new Section('layout_onecol');

    foreach ($blocks_config as $block_config) {
      $provider_id = $block_config['id'] ?? '';
      if (!str_starts_with($provider_id, 'block_content:')) {
        continue;
      }
      $uuid = explode(':', $provider_id)[1];

      // Look up the block by UUID, prefer the target-language translation.
      $blocks = $block_storage->loadByProperties(['uuid' => $uuid]);
      $block = $blocks ? reset($blocks) : NULL;
      if (!$block || $block->bundle() !== 'json') {
        continue;
      }

      // Use the block's revision ID for the target language.
      $vid = $block_config['vid'] ?? $block->getRevisionId();
      if ($block->hasTranslation($target_langcode)) {
        $vid = $block->getTranslation($target_langcode)->getRevisionId();
      }

      $configuration = [
        'id' => $provider_id,
        'label' => $block_config['label'] ?? $block->label(),
        'label_display' => (string) ($block_config['label_display'] ?? '0'),
        'status' => TRUE,
        'info' => $block_config['info'] ?? '',
        'view_mode' => $block_config['view_mode'] ?? 'full',
        'vid' => (int) $vid,
      ];

      $component_uuid = \Drupal::service('uuid')->generate();
      $component = new SectionComponent($component_uuid, 'content', $configuration);
      $section->appendComponent($component);
    }

    // Write the section to the translation's layout field.
    /** @var \Drupal\layout_builder\Field\LayoutSectionItemList $layout_field */
    $layout_field = $trans->get(OverridesSectionStorage::FIELD_NAME);
    $layout_field->setValue([]);
    $layout_field->appendItem($section);

    $trans->setNewRevision();
    $trans->save();

    $context['results']['synced'][] = "$nid:$target_langcode";
    $context['message'] = t('Migrated Panelizer→Layout Builder for "@title" (@lang)', [
      '@title' => $trans->label(),
      '@lang' => $target_langcode,
    ]);
  }

  /**
   * Checks whether a node has Layout Builder section data.
   *
   * @param \Drupal\node\NodeInterface $node
   * @return bool
   */
  private static function hasLayoutBuilderData($node) {
    if (!$node->hasField(OverridesSectionStorage::FIELD_NAME)) {
      return FALSE;
    }
    $field = $node->get(OverridesSectionStorage::FIELD_NAME);
    if ($field->isEmpty()) {
      return FALSE;
    }
    foreach ($field as $item) {
      if (!empty($item->section) && count($item->section->getComponents()) > 0) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   Whether the batch completed successfully.
   * @param array $results
   *   Results from processItem calls.
   * @param array $operations
   *   The operations that were processed.
   */
  public static function finished($success, array $results, array $operations) {
    $messenger = \Drupal::messenger();

    if ($success) {
      $synced = count($results['synced'] ?? []);
      $skipped = count($results['skipped'] ?? []);
      $errors = count($results['errors'] ?? []);

      $messenger->addStatus(t('Migration complete: @synced synced, @skipped skipped, @errors errors.', [
        '@synced' => $synced,
        '@skipped' => $skipped,
        '@errors' => $errors,
      ]));

      if (!empty($results['errors'])) {
        foreach ($results['errors'] as $error) {
          $messenger->addError($error);
        }
      }
    }
    else {
      $messenger->addError(t('Migration batch failed. Some items may not have been processed.'));
    }
  }

}
