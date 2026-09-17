<?php

use Xinshi\Migration\Runtime;

Runtime::requireEntryPoint();

/** Validate live model/plugin definitions without importing business rows. */
use Drupal\Core\Database\Database;
use Drupal\views\Views;

umask(0077);
if ((Database::getConnection()->getConnectionOptions()['database'] ?? '') !== Runtime::targetDatabase()) {
  throw new RuntimeException('Only the prepared model may be validated here.');
}
$collections = json_decode(file_get_contents(Runtime::path('model-config-plan.private.json')), TRUE, 512, JSON_THROW_ON_ERROR);
$storage = \Drupal::service('config.storage');
\Drupal::configFactory()->reset();
\Drupal::entityTypeManager()->clearCachedDefinitions();
\Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
\Drupal::service('views.views_data')->clear();
$report = ['status' => 'validating', 'config_counts' => array_map('count', $collections), 'missing_config' => [], 'display_plugin_errors' => [], 'view_errors' => [], 'view_count' => 0, 'widget_count' => 0, 'formatter_count' => 0, 'status_mismatches' => []];
$report['missing_migration_modules'] = array_values(array_filter(['migrate', 'migrate_tools', 'xinshi_migrate'], static fn($module) => !\Drupal::moduleHandler()->moduleExists($module)));
$report['migration_ownership_table_exists'] = Database::getConnection()->schema()->tableExists('xinshi_migrate_rows');
$privateErrors = [];
$report['translation_mismatches'] = [];
foreach (array_diff(array_keys($collections), ['']) as $collection) {
  $translated = $storage->createCollection($collection);
  foreach ($collections[$collection] as $name => $config) {
    if ($translated->read($name) !== $config) {
      $report['translation_mismatches'][] = ['collection' => $collection, 'name' => $name];
    }
  }
}

foreach ($collections[''] as $name => $config) {
  if (!$storage->exists($name)) {
    $report['missing_config'][] = $name;
    continue;
  }
  if (str_starts_with($name, 'core.entity_form_display.') || str_starts_with($name, 'core.entity_view_display.')) {
    $isForm = str_starts_with($name, 'core.entity_form_display.');
    $type = $isForm ? 'entity_form_display' : 'entity_view_display';
    $display = \Drupal::entityTypeManager()->getStorage($type)->load($config['id']);
    $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions($config['targetEntityType'], $config['bundle']);
    foreach ($config['content'] ?? [] as $fieldName => $component) {
      if (!isset($fields[$fieldName])) {
        continue;
      }
      $pluginId = $component['type'] ?? '';
      $pluginManager = \Drupal::service($isForm ? 'plugin.manager.field.widget' : 'plugin.manager.field.formatter');
      try {
        $definition = $pluginManager->getDefinition($pluginId);
        if (!in_array($fields[$fieldName]->getType(), $definition['field_types'] ?? [], TRUE)) {
          throw new RuntimeException('The configured plugin does not support the field type.');
        }
        $renderer = $display->getRenderer($fieldName);
        if (!$renderer || $renderer->getPluginId() !== $pluginId) {
          throw new RuntimeException('Drupal fell back to a different display plugin.');
        }
        $report[$isForm ? 'widget_count' : 'formatter_count']++;
      }
      catch (Throwable $error) {
        $report['display_plugin_errors'][] = ['config' => $name, 'field' => $fieldName, 'plugin' => $pluginId, 'field_type' => $fields[$fieldName]->getType()];
        $privateErrors[] = ['config' => $name, 'field' => $fieldName, 'message' => $error->getMessage()];
      }
    }
  }
  if (str_starts_with($name, 'views.view.')) {
    $report['view_count']++;
    try {
      $view = Views::getView($config['id']);
      $errors = $view->validate();
      if ($errors) {
        $report['view_errors'][$config['id']] = $errors;
      }
      if ((bool) $view->storage->status() !== (bool) $config['status']) {
        $report['status_mismatches'][] = $config['id'];
      }
      $view->destroy();
    }
    catch (Throwable $error) {
      $report['view_errors'][$config['id']] = ['exception' => get_class($error)];
      $privateErrors[] = ['config' => $name, 'message' => $error->getMessage()];
    }
  }
}
$report['status'] = $report['missing_config'] || $report['display_plugin_errors'] || $report['view_errors'] || $report['status_mismatches'] || $report['translation_mismatches'] || $report['missing_migration_modules'] || !$report['migration_ownership_table_exists'] ? 'needs_adaptation' : 'passed';
file_put_contents(Runtime::path('model-validation-report.json'), json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents(Runtime::path('model-validation-errors.private.json'), json_encode($privateErrors, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
print json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

if ($report['status'] !== 'passed') {
  throw new RuntimeException('Model validation did not pass.');
}
