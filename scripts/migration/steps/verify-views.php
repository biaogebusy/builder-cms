<?php

use Xinshi\Migration\Runtime;

Runtime::requireEntryPoint();

/** Execute retained Views queries and build representative real edit forms. */
use Drupal\Core\Database\Database;
use Drupal\views\Views;

umask(0077);
$started = microtime(TRUE);
$target = Database::getConnection();
$integrity = json_decode(file_get_contents(Runtime::path('full-snapshot-validation-report.json')), TRUE, 512, JSON_THROW_ON_ERROR);
if ($target->getConnectionOptions()['database'] !== Runtime::targetDatabase() || $integrity['status'] !== 'passed') {
  throw new RuntimeException('The verified prepared target is required.');
}
$target->query('SET SESSION wait_timeout = 7200');
$manager = \Drupal::entityTypeManager();
$switcher = \Drupal::service('account_switcher');
$switcher->switchTo($manager->getStorage('user')->load(1));
$report = ['status' => 'checking', 'views' => [], 'forms' => [], 'physical_file_previews_verified' => FALSE, 'business_forms_submitted' => FALSE, 'execution_environment' => Runtime::environment()];
$errors = [];
$recordError = static function ($type, $id, Throwable $error) use (&$errors): void {
  $errors[] = ['type' => $type, 'id' => $id, 'class' => get_class($error), 'message' => $error->getMessage(), 'trace' => $error->getTraceAsString()];
};
try {
  $plan = json_decode(file_get_contents(Runtime::path('model-config-plan-report.json')), TRUE, 512, JSON_THROW_ON_ERROR);
  foreach (array_keys($plan['view_policies']) as $name) {
    $id = substr($name, strlen('views.view.'));
    $entity = $manager->getStorage('view')->load($id);
    $stats = ['enabled' => $entity->status(), 'executed_displays' => 0, 'disabled_displays' => 0, 'argument_or_exposed_input_required' => 0, 'errors' => 0];
    if ($entity->status()) {
      foreach ($entity->get('display') as $displayId => $display) {
        try {
          $manager->getStorage('view')->resetCache([$id]);
          $view = Views::getView($id);
          if (!$view->setDisplay($displayId)) {
            throw new RuntimeException('Display could not be initialized.');
          }
          if (!$view->display_handler->isEnabled()) {
            $stats['disabled_displays']++;
            continue;
          }
          // Limit runtime samples in memory only; keep persisted display settings.
          $view->display_handler->setOption('pager', ['type' => 'some', 'options' => ['items_per_page' => 2, 'offset' => 0]]);
          $view->display_handler->setOption('cache', ['type' => 'none']);
          $view->setItemsPerPage(2);
          $view->setOffset(0);
          $view->setCurrentPage(0);
          $view->preExecute([]);
          $executed = $view->execute();
          if (!empty($view->build_info['fail']) && !$executed) {
            $stats['argument_or_exposed_input_required']++;
          }
          elseif ($executed) {
            $stats['executed_displays']++;
          }
          else {
            throw new RuntimeException('View query did not execute.');
          }
          $view->postExecute();
          $view->destroy();
        }
        catch (Throwable $error) {
          $stats['errors']++;
          $recordError('view', $id . ':' . $displayId, $error);
        }
      }
    }
    $report['views'][$id] = $stats;
    if (count($report['views']) % 10 === 0) {
      print json_encode(['views_checked' => count($report['views']), 'errors' => count($errors)], JSON_THROW_ON_ERROR) . PHP_EOL;
    }
  }
  $bundles = \Drupal::service('entity_type.bundle.info');
  foreach (['node', 'block_content', 'media', 'taxonomy_term', 'user'] as $type) {
    $storage = $manager->getStorage($type);
    $definition = $manager->getDefinition($type);
    foreach (array_keys($bundles->getBundleInfo($type)) as $bundle) {
      $id = $type . '.' . $bundle;
      try {
        $query = $storage->getQuery()->accessCheck(FALSE)->range(0, 1);
        if ($definition->hasKey('bundle')) {
          $query->condition($definition->getKey('bundle'), $bundle);
        }
        if ($type === 'user') {
          $query->condition('uid', 9);
        }
        $ids = $query->execute();
        $entity = $ids ? $storage->load(reset($ids)) : $storage->create([$definition->getKey('bundle') => $bundle]);
        $operation = $definition->getFormClass('edit') ? 'edit' : 'default';
        $form = \Drupal::service('entity.form_builder')->getForm($entity, $operation);
        if (!is_array($form) || empty($form['#form_id'])) {
          throw new RuntimeException('Edit form did not build.');
        }
        $report['forms'][$id] = ['passed' => TRUE, 'existing_entity' => !$entity->isNew()];
      }
      catch (Throwable $error) {
        $report['forms'][$id] = ['passed' => FALSE];
        $recordError('form', $id, $error);
      }
      unset($form, $entity);
      $storage->resetCache();
    }
  }
}
finally {
  $switcher->switchBack();
}
$report['error_count'] = count($errors);
$report['status'] = $errors ? 'failed' : 'passed';
$report['elapsed_seconds'] = round(microtime(TRUE) - $started, 2);
file_put_contents(Runtime::path('views-and-forms-report.json'), json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
if ($errors) {
  file_put_contents(Runtime::path('views-and-forms-errors.private.json'), json_encode($errors, JSON_THROW_ON_ERROR));
}
print json_encode(['status' => $report['status'], 'views' => count($report['views']), 'executed_displays' => array_sum(array_column($report['views'], 'executed_displays')), 'forms' => count($report['forms']), 'error_count' => count($errors), 'elapsed_seconds' => $report['elapsed_seconds']], JSON_THROW_ON_ERROR) . PHP_EOL;
if ($errors) {
  exit(1);
}
