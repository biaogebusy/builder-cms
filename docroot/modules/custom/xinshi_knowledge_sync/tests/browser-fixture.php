<?php

declare(strict_types=1);

/**
 * @file
 * Enables core admin UI rendering only in the disposable browser fixture.
 */

use Drupal\block\Entity\Block;

if (getenv('XINSHI_KNOWLEDGE_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') ||
    Drupal::root() !== '/site/docroot' ||
    Drupal::database()->getConnectionOptions()['database'] !== '/tmp/xinshi-knowledge-test/site.sqlite') {
  throw new RuntimeException('Use the isolated knowledge runner only.');
}
Drupal::service('theme_installer')->install(['claro']);
Drupal::configFactory()->getEditable('system.theme')->set('default', 'claro')->set('admin', 'claro')->save();
foreach (['system_main_block' => 'content', 'page_title_block' => 'header', 'system_messages_block' => 'highlighted'] as $plugin => $region) {
  $existing = array_filter(Block::loadMultiple(), fn($block) => $block->getTheme() === 'claro' && $block->getPluginId() === $plugin);
  if (!$existing) {
    Block::create(['id' => 'sync_test_' . $plugin, 'theme' => 'claro', 'region' => $region,
      'plugin' => $plugin, 'settings' => ['label_display' => FALSE], 'status' => TRUE])->save();
  }
}
Drupal::configFactory()->getEditable('system.performance')->set('css.preprocess', FALSE)->set('js.preprocess', FALSE)->save();
Drupal::service('router.builder')->rebuild();
