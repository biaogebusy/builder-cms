<?php

declare(strict_types=1);

/**
 * @file
 * Form API and access checks on the disposable site created by integration.php.
 */

use Drupal\Core\Form\FormState;
use Drupal\Core\Lock\DatabaseLockBackend;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\xinshi_knowledge_sync\Form\SourceForm;
use Drupal\xinshi_knowledge_sync\Service\SourceAccess;

/** Opens a form with the route argument using FormBuilder's build-info contract. */
function openSource(?string $id, FormState $state): array {
  $state->addBuildInfo('args', [$id]);
  return Drupal::formBuilder()->buildForm(SourceForm::class, $state);
}

/** Submits through the real Form API, including element validation. */
function submitSource(?string $id, array $values, ?FormState $state = NULL): FormState {
  $state ??= new FormState();
  $form = openSource($id, $state);
  // Omitted rows model AJAX removal before a programmatic submit.
  $state->set('rules_rows', array_values(array_intersect(
    $state->get('rules_rows'), array_keys($values['rules'] ?? []))));
  $state->setValues(['settings' => $values, 'op' => '保存来源',
    'source_fingerprint' => $form['source_fingerprint']['#default_value']]);
  Drupal::formBuilder()->submitForm(SourceForm::class, $state, $id);
  return $state;
}

/** Converts stored policies to browser form values. */
function sourceValues(array $source): array {
  $source['enabled'] = $source['enabled'] ? 1 : NULL;
  foreach ($source['policies'] as &$policy) {
    $policy['all_readers'] = $policy['all_readers'] ? 1 : NULL;
    $policy['roles'] = array_combine($policy['roles'], $policy['roles']);
    $policy['users'] = implode(', ', array_map(fn($uid) => User::load($uid)->label() . " ($uid)", $policy['users']));
  }
  return $source;
}

(function (): void {
  $configName = 'xinshi_knowledge_sync.settings';
  $originalSources = Drupal::config($configName)->get('sources');
  $permission = 'administer xinshi knowledge sync';
  foreach (['sync_config_admin' => [$permission], 'sync_parser_admin' => ['administer xinshi knowledge']] as $rid => $grants) {
    Role::create(['id' => $rid, 'label' => $rid, 'permissions' => $grants])->save();
    User::create(['name' => $rid, 'pass' => 'synthetic-form-test-only', 'status' => 1, 'roles' => [$rid]])->save();
  }
  $configAdmin = user_load_by_name('sync_config_admin');
  $parserAdmin = user_load_by_name('sync_parser_admin');
  $reader = user_load_by_name('reader');
  $reader->setPassword('synthetic-form-test-only')->save();
  $blocked = User::create(['name' => 'blocked-form-reader', 'status' => 0]);
  $blocked->save();
  Drupal::service('router.builder')->rebuild();
  foreach ([new AnonymousUserSession(), $reader, $parserAdmin, $configAdmin] as $account) {
    foreach (['settings', 'source_add', 'source_edit'] as $route) {
      check(Drupal::service('access_manager')->checkNamedRoute('xinshi_knowledge_sync.' . $route,
        ['source_id' => 'product'], $account) === ($account === $configAdmin), 'Source route permission mismatch: ' . $route);
    }
  }
  check(!Role::load('reader')->hasPermission($permission) && !Role::load('sync_parser_admin')->hasPermission($permission), 'New permission was granted to existing roles.');
  $menu = Drupal::service('plugin.manager.menu.link')->getDefinition('xinshi_knowledge_sync.settings');
  check($menu['parent'] === 'system.admin_config_content', 'Standalone module menu fallback failed.');
  Drupal::currentUser()->setAccount($configAdmin);

  $state = new FormState();
  openSource(NULL, $state);
  $defaults = $state->get('source_defaults');
  $defaults['id'] = 'form-defaults';
  $defaults['enabled'] = TRUE;
  $state = submitSource(NULL, sourceValues($defaults), $state);
  check(!$state->hasAnyErrors() && !$state->isRebuilding(), 'Prefilled source could not be saved.');
  $access = Drupal::service('xinshi_knowledge_sync.access');
  check($access->allowed('form-defaults', 'readers', $reader), 'Prefilled role does not admit a knowledge reader.');
  check(!$access->allowed('form-defaults', 'readers', $parserAdmin) &&
    !$access->allowed('form-defaults', 'readers', new AnonymousUserSession()), 'Prefilled role bypassed baseline knowledge permissions.');
  $defaults['policies'][0]['roles'] = ['operations'];
  check(!submitSource('form-defaults', sourceValues($defaults))->hasAnyErrors(), 'Could not narrow the prefilled reader role.');
  check(!$access->allowed('form-defaults', 'readers', $reader), 'All-readers flag bypassed the narrowed role selection.');
  Drupal::configFactory()->getEditable($configName)->set('sources', $originalSources)->save();

  $basic = ['id' => 'form-created', 'enabled' => TRUE, 'default_policy' => 'readers',
    'policies' => [['id' => 'readers', 'all_readers' => TRUE, 'roles' => [], 'users' => []]], 'rules' => []];
  $state = submitSource(NULL, sourceValues($basic));
  check(!$state->hasAnyErrors() && !$state->isRebuilding(), 'Valid source form failed: ' . implode('; ', $state->getErrors()));
  $stored = Drupal::config($configName)->get('sources');
  check(array_pop($stored) === $basic && $stored === $originalSources, 'Create changed another source or stored malformed values.');
  $before = Drupal::config($configName)->getRawData();
  check(submitSource(NULL, sourceValues($basic))->hasAnyErrors(), 'Duplicate source accepted.');
  check(Drupal::config($configName)->getRawData() === $before, 'Invalid submission changed configuration.');

  $state = new FormState();
  openSource(NULL, $state);
  $state->set('policies_rows', [0, 1]);
  $values = sourceValues($basic);
  $values['id'] = 'invalid-policies';
  $values['policies'][] = $values['policies'][0];
  check(submitSource(NULL, $values, $state)->hasAnyErrors(), 'Duplicate policy identifier accepted.');

  $fixture = ['id' => 'form-fixture', 'enabled' => TRUE, 'default_policy' => 'readers',
    'policies' => [
      ['id' => 'readers', 'all_readers' => TRUE, 'roles' => [], 'users' => []],
      ['id' => 'operations', 'all_readers' => FALSE, 'roles' => ['operations'], 'users' => [(int) $reader->id()]],
      ['id' => 'spare', 'all_readers' => FALSE, 'roles' => [], 'users' => []],
    ], 'rules' => [['prefix' => 'internal/', 'policy' => 'operations']]];
  $sources = [...Drupal::config($configName)->get('sources'), $fixture];
  Drupal::configFactory()->getEditable($configName)->set('sources', $sources)->save();
  Drupal::currentUser()->setAccount(User::load(1));
  Drupal::service('xinshi_knowledge_sync.importer')->import(snapshot('form-fixture', ['help.md', 'internal/staff.md']), 'form-fixture', User::load(1), TRUE);
  Drupal::currentUser()->setAccount($configAdmin);
  $ledger = Drupal::database()->select(SourceAccess::TABLE, 'd')->fields('d')->orderBy('nid')->execute()->fetchAll(PDO::FETCH_ASSOC);
  $nodes = Drupal::database()->select('node_field_data', 'n')->fields('n')->orderBy('nid')->execute()->fetchAll(PDO::FETCH_ASSOC);
  $before = Drupal::config($configName)->getRawData();
  foreach (['../private/', '/absolute/', 'internal//', 'internal/./', 'internal/../', 'internal', "internal\\private/"] as $prefix) {
    $values = sourceValues($fixture);
    $values['rules'][0]['prefix'] = $prefix;
    check(submitSource('form-fixture', $values)->hasAnyErrors(), 'Invalid directory accepted: ' . $prefix);
  }
  foreach (['id' => 'Bad ID', 'default_policy' => 'missing'] as $field => $value) {
    $values = sourceValues($basic);
    $values[$field] = $value;
    check(submitSource(NULL, $values)->hasAnyErrors(), 'Invalid source field accepted: ' . $field);
  }
  $values = sourceValues($fixture);
  $values['policies'][1]['users'] = 'blocked-form-reader (' . $blocked->id() . ')';
  check(submitSource('form-fixture', $values)->hasAnyErrors(), 'Blocked policy account accepted.');
  $values['policies'][1]['users'] = 'missing (999999)';
  check(submitSource('form-fixture', $values)->hasAnyErrors(), 'Missing policy account accepted.');
  $values = sourceValues($fixture);
  $values['policies'][1]['roles'] = ['missing_role' => 'missing_role'];
  check(submitSource('form-fixture', $values)->hasAnyErrors(), 'Unknown policy role accepted.');
  $values = sourceValues($fixture);
  $values['rules'][0]['policy'] = 'missing';
  check(submitSource('form-fixture', $values)->hasAnyErrors(), 'Unknown rule policy accepted.');
  $state = new FormState();
  openSource('form-fixture', $state);
  $state->set('rules_rows', [0, 1]);
  $values = sourceValues($fixture);
  $values['rules'][] = $values['rules'][0];
  check(submitSource('form-fixture', $values, $state)->hasAnyErrors(), 'Duplicate directory prefix accepted.');
  check(Drupal::config($configName)->getRawData() === $before, 'Invalid form values changed configuration.');

  $state = new FormState();
  openSource('form-fixture', $state);
  $state->set('policies_rows', [0, 2])->set('rules_rows', []);
  check(submitSource('form-fixture', sourceValues($fixture), $state)->hasAnyErrors(), 'Referenced policy removal accepted.');
  $state = new FormState();
  openSource('form-fixture', $state);
  $state->set('policies_rows', []);
  check(submitSource('form-fixture', sourceValues($fixture), $state)->hasAnyErrors(), 'Empty policies accepted.');

  $lock = new DatabaseLockBackend(Drupal::database());
  check($lock->acquire('xinshi_knowledge_sync.import'), 'Could not acquire competing import lock.');
  try {
    $state = submitSource('form-fixture', sourceValues($fixture));
    check($state->isRebuilding(), 'Configuration save did not reach lock handling: ' . implode('; ', $state->getErrors()));
    check(Drupal::config($configName)->getRawData() === $before, 'Locked save changed configuration.');
  }
  finally {
    $lock->release('xinshi_knowledge_sync.import');
  }
  $stale = new FormState();
  openSource('form-fixture', $stale);
  $last = array_key_last($sources);
  $sources[$last]['enabled'] = FALSE;
  Drupal::configFactory()->getEditable($configName)->set('sources', $sources)->save();
  check(submitSource('form-fixture', sourceValues($fixture), $stale)->hasAnyErrors(), 'Concurrent source edit was overwritten.');
  check(Drupal::config($configName)->get('sources')[$last]['enabled'] === FALSE, 'Stale form restored the source.');

  $state = submitSource('form-fixture', sourceValues($fixture));
  check(!$state->hasAnyErrors() && !$state->isRebuilding(), 'Existing configuration failed to round-trip.');
  check(Drupal::config($configName)->get('sources')[$last] === $fixture, 'Role/user policy did not round-trip.');
  check(Drupal::database()->select(SourceAccess::TABLE, 'd')->fields('d')->orderBy('nid')->execute()->fetchAll(PDO::FETCH_ASSOC) === $ledger, 'Form save modified the sync ledger.');
  check(Drupal::database()->select('node_field_data', 'n')->fields('n')->orderBy('nid')->execute()->fetchAll(PDO::FETCH_ASSOC) === $nodes, 'Form save modified document nodes.');
  $nid = (int) Drupal::database()->select(SourceAccess::TABLE, 'd')->fields('d', ['nid'])
    ->condition('source', 'form-fixture')->condition('path', 'internal/staff.md')->execute()->fetchField();
  resetAccess();
  check(Node::load($nid)->access('view', $reader), 'Configured user cannot read fixture.');
  $values = sourceValues($fixture);
  $values['policies'][1]['users'] = '';
  check(!submitSource('form-fixture', $values)->hasAnyErrors(), 'Policy membership save failed.');
  resetAccess();
  check(!Node::load($nid)->access('view', $reader), 'Form membership change did not revoke current reads.');
  submitSource('form-fixture', sourceValues($fixture));
  $values = sourceValues($fixture);
  $values['enabled'] = NULL;
  check(!submitSource('form-fixture', $values)->hasAnyErrors(), 'Source disable form failed.');
  resetAccess();
  check(!Node::load($nid)->access('view', $reader), 'Form source disable did not revoke reads.');
  submitSource('form-fixture', sourceValues($fixture));

  // Model an import completing between validation and the locked save.
  $race = $basic;
  $race['id'] = 'form-race';
  $race['policies'][] = ['id' => 'pending', 'all_readers' => FALSE, 'roles' => [], 'users' => []];
  $race['rules'][] = ['prefix' => 'pending/', 'policy' => 'pending'];
  Drupal::configFactory()->getEditable($configName)
    ->set('sources', [...Drupal::config($configName)->get('sources'), $race])->save();
  $formObject = SourceForm::create(Drupal::getContainer());
  $state = new FormState();
  $form = $formObject->buildForm([], $state, 'form-race');
  $state->clearErrors();
  $state->set('policies_rows', [0])->set('rules_rows', []);
  $state->setValues(['settings' => $race, 'source_fingerprint' => $form['source_fingerprint']['#default_value']]);
  $formObject->validateForm($form, $state);
  check(!$state->hasAnyErrors(), 'Unused policy removal was rejected before the import.');
  Drupal::service('xinshi_knowledge_sync.importer')->import(snapshot('form-race', ['pending/new.md']), 'form-race', User::load(1), TRUE);
  $formObject->submitForm($form, $state);
  check($state->isRebuilding(), 'Policy gained references during validation but was removed.');
  $stored = Drupal::config($configName)->get('sources');
  check(end($stored) === $race, 'Save did not recheck policy references under the lock.');

  $optional = ['id' => 'optional-fields', 'enabled' => TRUE, 'default_policy' => 'readers',
    'policies' => [['id' => 'readers', 'all_readers' => TRUE]]];
  Drupal::configFactory()->getEditable($configName)
    ->set('sources', [...Drupal::config($configName)->get('sources'), $optional])->save();
  $values = sourceValues($basic);
  $values['id'] = 'optional-fields';
  check(!submitSource('optional-fields', $values)->hasAnyErrors(), 'Existing source with omitted optional fields could not be edited.');
  Drupal::currentUser()->setAccount(User::load(1));

  // The same synthetic accounts and data are used by the optional browser checks.
  file_put_contents('/tmp/xinshi-knowledge-test/form-fixture.json', json_encode(['reader' => (int) $reader->id(), 'privateNode' => $nid], JSON_THROW_ON_ERROR));
})();
