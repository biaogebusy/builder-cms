<?php

/**
 * @file
 * Builds actual Drupal configuration importers for disposable site checks.
 */

use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Config\MemoryStorage;
use Drupal\Core\Config\StorageComparer;

function importSource(): MemoryStorage {
  $source = new MemoryStorage();
  $active = \Drupal::service('config.storage');
  foreach ($active->listAll() as $name) {
    $source->write($name, $active->read($name));
  }
  return $source;
}
function importer(MemoryStorage $source): ConfigImporter {
  $comparer = new StorageComparer($source, \Drupal::service('config.storage'));
  $comparer->createChangelist();
  return new ConfigImporter($comparer, \Drupal::service('event_dispatcher'),
    \Drupal::service('config.manager'), \Drupal::service('lock.persistent'), \Drupal::service('config.typed'),
    \Drupal::moduleHandler(), \Drupal::service('module_installer'), \Drupal::service('theme_handler'),
    \Drupal::translation(), \Drupal::service('extension.list.module'), \Drupal::service('extension.list.theme'));
}
