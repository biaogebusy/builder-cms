<?php

use Xinshi\Migration\Runtime;

Runtime::requireEntryPoint();

/** Import reviewed config entities through their storage APIs in the local copy. */
use Drupal\Core\Database\Database;

umask(0077);
$db = Database::getConnection();
if (($db->getConnectionOptions()['database'] ?? '') !== Runtime::targetDatabase()
  || !file_exists(Runtime::path('target-before-model-backup.json'))) {
  throw new RuntimeException('The prepared target and baseline backup are required.');
}
$collections = json_decode(file_get_contents(Runtime::path('model-config-plan.private.json')), TRUE, 512, JSON_THROW_ON_ERROR);
$source = $collections[''];
$report = ['status' => 'running', 'saved' => [], 'unchanged' => 0, 'role_permissions_removed' => [], 'environment_adaptations' => [], 'translation_counts' => []];
$active = \Drupal::service('config.storage');
$manager = \Drupal::service('config.manager');
$entityManager = \Drupal::entityTypeManager();
$pending = $source;
$done = [];
$currentName = NULL;
$saveReport = static function () use (&$report): void {
  file_put_contents(Runtime::path('model-config-install-report.json'), json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
};

try {
  $keys = Runtime::oauthSigningFiles();
  foreach ($keys as $setting => $filename) {
    $pending['simple_oauth.settings'][$setting] = $filename;
  }
  $report['environment_adaptations'][] = Runtime::environment() === 'local' ? 'Isolated local signing files.' : 'Explicitly configured target signing files.';
  // Roles depend on dynamically generated permissions and are saved after models.
  $roles = array_filter($pending, fn($name) => str_starts_with($name, 'user.role.'), ARRAY_FILTER_USE_KEY);
  $pending = array_diff_key($pending, $roles);
  $saveEntity = static function (string $name, array $data) use ($manager, $entityManager, $active, &$report): void {
    $type = $manager->getEntityTypeIdByName($name);
    $existingData = $active->read($name);
    if ($existingData && isset($existingData['uuid'])) {
      $data['uuid'] = $existingData['uuid'];
    }
    unset($data['_core']);
    if (!$type) {
      if (isset($data['uuid'], $data['id'])) {
        throw new RuntimeException('No installed config entity provider for ' . $name);
      }
      $comparable = $existingData;
      unset($comparable['_core']);
      if ($comparable === $data) {
        $report['unchanged']++;
        return;
      }
      \Drupal::configFactory()->getEditable($name)->setData($data)->save();
      return;
    }
    $definition = $entityManager->getDefinition($type);
    $idKey = $definition->getKey('id');
    $storage = $entityManager->getStorage($type);
    $id = $data[$idKey] ?? substr($name, strlen($definition->getConfigPrefix()) + 1);
    $entity = $storage->load($id);
    if ($entity) {
      $entity = $storage->updateFromStorageRecord($entity, $data);
    }
    else {
      $entity = $storage->createFromStorageRecord($data);
    }
    $comparable = $existingData;
    unset($comparable['_core']);
    $proposed = $entity->toArray();
    unset($proposed['_core']);
    if ($comparable === $proposed) {
      $report['unchanged']++;
      return;
    }
    $entity->setSyncing(TRUE);
    $entity->save();
    $entity->setSyncing(FALSE);
  };
  // Referenced roles must exist before Views with role-based access are created.
  foreach ($roles as $name => $role) {
    if (!$active->exists($name)) {
      $placeholder = $role;
      $placeholder['permissions'] = [];
      $placeholder['dependencies'] = [];
      $saveEntity($name, $placeholder);
    }
  }
  while ($pending) {
    $progress = FALSE;
    foreach ($pending as $name => $data) {
      $ready = TRUE;
      foreach (($data['dependencies'] ?? [])['config'] ?? [] as $dependency) {
        if (isset($pending[$dependency])) {
          $ready = FALSE;
          break;
        }
        if (!isset($done[$dependency]) && !$active->exists($dependency)) {
          throw new RuntimeException('Missing config dependency for ' . $name . ': ' . $dependency);
        }
      }
      if (!$ready) {
        continue;
      }
      $currentName = $name;
      $saveEntity($name, $data);
      $report['saved'][] = $name;
      $done[$name] = TRUE;
      unset($pending[$name]);
      $progress = TRUE;
      if (count($report['saved']) % 25 === 0) {
        $saveReport();
        print json_encode(['config_saved' => count($report['saved']), 'remaining' => count($pending)], JSON_THROW_ON_ERROR) . PHP_EOL;
      }
    }
    if (!$progress) {
      throw new RuntimeException('Configuration dependency cycle: ' . implode(', ', array_keys($pending)));
    }
  }
  // Preserve source permissions that the retained models and providers implement.
  \Drupal::service('router.builder')->rebuild();
  $permissionNames = array_keys(\Drupal::service('user.permissions')->getPermissions());
  foreach ($roles as $name => $role) {
    $currentName = $name;
    $permissions = $role['permissions'] ?? [];
    $role['permissions'] = array_values(array_intersect($permissions, $permissionNames));
    $report['role_permissions_removed'][$name] = array_values(array_diff($permissions, $role['permissions']));
    $role['dependencies'] = [];
    $saveEntity($name, $role);
    $entity = \Drupal::entityTypeManager()->getStorage('user_role')->load($role['id']);
    $entity->calculateDependencies()->save();
    $report['saved'][] = $name;
  }
  foreach (array_diff(array_keys($collections), ['']) as $collection) {
    $storage = $active->createCollection($collection);
    $report['translation_counts'][$collection] = 0;
    foreach ($collections[$collection] as $name => $data) {
      if (!$active->exists($name)) {
        throw new RuntimeException('Translation has no base config: ' . $name);
      }
      $storage->write($name, $data);
      $report['translation_counts'][$collection]++;
    }
  }
  \Drupal::configFactory()->reset();
  \Drupal::entityTypeManager()->clearCachedDefinitions();
  \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
  $report['status'] = 'installed';
  $report['config_count'] = count($report['saved']);
  $report['legacy_binding_entity'] = \Drupal::entityTypeManager()->hasDefinition('wechat_user');
  $report['legacy_widgets'] = [];
  foreach (['xinshi_media_library_widget', 'xinshi_moderation_state_button'] as $plugin) {
    $definition = \Drupal::service('plugin.manager.field.widget')->getDefinition($plugin);
    $report['legacy_widgets'][$plugin] = $definition['provider'];
  }
  $report['target_accounts'] = (int) Database::getConnection()->query('SELECT COUNT(*) FROM users')->fetchField();
  $saveReport();
  print json_encode(array_diff_key($report, array_flip(['saved', 'role_permissions_removed'])), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
}
catch (Throwable $error) {
  $report['status'] = 'failed';
  $report['failed_config'] = $currentName;
  $saveReport();
  file_put_contents(Runtime::path('model-config-install-error.private.json'), json_encode(['config' => $currentName, 'class' => get_class($error), 'message' => $error->getMessage(), 'trace' => $error->getTraceAsString()], JSON_THROW_ON_ERROR));
  print json_encode(['status' => 'failed', 'config' => $currentName, 'error_class' => get_class($error), 'details' => 'model-config-install-error.private.json'], JSON_THROW_ON_ERROR) . PHP_EOL;
  exit(1);
}
