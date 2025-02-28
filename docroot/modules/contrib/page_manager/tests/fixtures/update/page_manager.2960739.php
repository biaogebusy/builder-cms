<?php

/**
 * @file
 * Test fixture for task #2960739.
 *
 * @see https://www.drupal.org/project/page_manager/issues/2960739
 */

use Drupal\Core\Database\Database;
use Drupal\Core\Serialization\Yaml;

$connection = Database::getConnection();

$ext = $connection->select('config')
  ->fields('config', ['data'])
  ->where("name = 'core.extension'")
  ->execute();

$ext = unserialize($ext->fetchObject()->data);
$ext['module']['page_manager'] = 0;
$ext['module']['ctools'] = 0;

$connection->update('config')
  ->fields([
    'data' => serialize($ext),
  ])
  ->where("name = 'core.extension'")
  ->execute();

$connection->insert('key_value')
  ->fields([
    'collection' => 'entity.definitions.installed',
    'name' => 'page.entity_type',
    'value' => 'O:42:"Drupal\Core\Config\Entity\ConfigEntityType":44:{s:16:" * config_prefix";N;s:15:" * static_cache";b:0;s:14:" * lookup_keys";a:1:{i:0;s:4:"uuid";}s:16:" * config_export";a:8:{i:0;s:2:"id";i:1;s:5:"label";i:2;s:11:"description";i:3;s:15:"use_admin_theme";i:4;s:4:"path";i:5;s:12:"access_logic";i:6;s:17:"access_conditions";i:7;s:10:"parameters";}s:21:" * mergedConfigExport";a:0:{}s:15:" * render_cache";b:1;s:19:" * persistent_cache";b:1;s:14:" * entity_keys";a:9:{s:2:"id";s:2:"id";s:5:"label";s:5:"label";s:6:"status";s:6:"status";s:8:"revision";s:0:"";s:6:"bundle";s:0:"";s:8:"langcode";s:8:"langcode";s:16:"default_langcode";s:16:"default_langcode";s:29:"revision_translation_affected";s:29:"revision_translation_affected";s:4:"uuid";s:4:"uuid";}s:5:" * id";s:4:"page";s:16:" * originalClass";s:31:"Drupal\page_manager\Entity\Page";s:11:" * handlers";a:2:{s:6:"access";s:37:"Drupal\page_manager\Entity\PageAccess";s:7:"storage";s:45:"Drupal\Core\Config\Entity\ConfigEntityStorage";}s:19:" * admin_permission";s:16:"administer pages";s:25:" * permission_granularity";s:11:"entity_type";s:8:" * links";a:0:{}s:17:" * label_callback";N;s:21:" * bundle_entity_type";N;s:12:" * bundle_of";N;s:15:" * bundle_label";N;s:13:" * base_table";N;s:22:" * revision_data_table";N;s:17:" * revision_table";N;s:13:" * data_table";N;s:11:" * internal";b:0;s:15:" * translatable";b:0;s:19:" * show_revision_ui";b:0;s:8:" * label";O:48:"Drupal\Core\StringTranslation\TranslatableMarkup":3:{s:9:" * string";s:4:"Page";s:12:" * arguments";a:0:{}s:10:" * options";a:0:{}}s:19:" * label_collection";s:0:"";s:17:" * label_singular";s:0:"";s:15:" * label_plural";s:0:"";s:14:" * label_count";a:0:{}s:15:" * uri_callback";N;s:8:" * group";s:13:"configuration";s:14:" * group_label";O:48:"Drupal\Core\StringTranslation\TranslatableMarkup":3:{s:9:" * string";s:13:"Configuration";s:12:" * arguments";a:0:{}s:10:" * options";a:1:{s:7:"context";s:17:"Entity type group";}}s:22:" * field_ui_base_route";N;s:26:" * common_reference_target";b:0;s:22:" * list_cache_contexts";a:0:{}s:18:" * list_cache_tags";a:1:{i:0;s:16:"config:page_list";}s:14:" * constraints";a:0:{}s:13:" * additional";a:0:{}s:8:" * class";s:31:"Drupal\page_manager\Entity\Page";s:11:" * provider";s:12:"page_manager";s:14:" * _serviceIds";a:0:{}s:18:" * _entityStorages";a:0:{}s:20:" * stringTranslation";N;}',
  ])
  ->execute();
$connection->insert('key_value')
  ->fields([
    'collection' => 'entity.definitions.installed',
    'name' => 'page_variant.entity_type',
    'value' => 'O:42:"Drupal\Core\Config\Entity\ConfigEntityType":44:{s:16:" * config_prefix";N;s:15:" * static_cache";b:0;s:14:" * lookup_keys";a:2:{i:0;s:4:"page";i:1;s:4:"uuid";}s:16:" * config_export";a:10:{i:0;s:2:"id";i:1;s:5:"label";i:2;s:4:"uuid";i:3;s:7:"variant";i:4;s:16:"variant_settings";i:5;s:4:"page";i:6;s:6:"weight";i:7;s:18:"selection_criteria";i:8;s:15:"selection_logic";i:9;s:14:"static_context";}s:21:" * mergedConfigExport";a:0:{}s:15:" * render_cache";b:1;s:19:" * persistent_cache";b:1;s:14:" * entity_keys";a:8:{s:2:"id";s:2:"id";s:5:"label";s:5:"label";s:4:"uuid";s:4:"uuid";s:8:"revision";s:0:"";s:6:"bundle";s:0:"";s:8:"langcode";s:8:"langcode";s:16:"default_langcode";s:16:"default_langcode";s:29:"revision_translation_affected";s:29:"revision_translation_affected";}s:5:" * id";s:12:"page_variant";s:16:" * originalClass";s:38:"Drupal\page_manager\Entity\PageVariant";s:11:" * handlers";a:3:{s:12:"view_builder";s:49:"Drupal\page_manager\Entity\PageVariantViewBuilder";s:6:"access";s:44:"Drupal\page_manager\Entity\PageVariantAccess";s:7:"storage";s:45:"Drupal\Core\Config\Entity\ConfigEntityStorage";}s:19:" * admin_permission";s:16:"administer pages";s:25:" * permission_granularity";s:11:"entity_type";s:8:" * links";a:0:{}s:17:" * label_callback";N;s:21:" * bundle_entity_type";N;s:12:" * bundle_of";N;s:15:" * bundle_label";N;s:13:" * base_table";N;s:22:" * revision_data_table";N;s:17:" * revision_table";N;s:13:" * data_table";N;s:11:" * internal";b:0;s:15:" * translatable";b:0;s:19:" * show_revision_ui";b:0;s:8:" * label";O:48:"Drupal\Core\StringTranslation\TranslatableMarkup":3:{s:9:" * string";s:12:"Page Variant";s:12:" * arguments";a:0:{}s:10:" * options";a:0:{}}s:19:" * label_collection";s:0:"";s:17:" * label_singular";s:0:"";s:15:" * label_plural";s:0:"";s:14:" * label_count";a:0:{}s:15:" * uri_callback";N;s:8:" * group";s:13:"configuration";s:14:" * group_label";O:48:"Drupal\Core\StringTranslation\TranslatableMarkup":3:{s:9:" * string";s:13:"Configuration";s:12:" * arguments";a:0:{}s:10:" * options";a:1:{s:7:"context";s:17:"Entity type group";}}s:22:" * field_ui_base_route";N;s:26:" * common_reference_target";b:0;s:22:" * list_cache_contexts";a:0:{}s:18:" * list_cache_tags";a:1:{i:0;s:24:"config:page_variant_list";}s:14:" * constraints";a:0:{}s:13:" * additional";a:0:{}s:8:" * class";s:38:"Drupal\page_manager\Entity\PageVariant";s:11:" * provider";s:12:"page_manager";s:14:" * _serviceIds";a:0:{}s:18:" * _entityStorages";a:0:{}s:20:" * stringTranslation";N;}',
  ])
  ->execute();

$connection->insert('config')
  ->fields([
    'collection' => '',
    'name' => 'page_manager.page.test_page',
    'data' => serialize(Yaml::decode(file_get_contents(__DIR__ . '/page_manager.page.test_page.yml'))),
  ])
  ->execute();

$connection->insert('config')
  ->fields([
    'collection' => '',
    'name' => 'page_manager.page_variant.test_page-block_display-0',
    'data' => serialize(Yaml::decode(file_get_contents(__DIR__ . '/page_manager.page_variant.test_page-block_display-0.yml'))),
  ])
  ->execute();
