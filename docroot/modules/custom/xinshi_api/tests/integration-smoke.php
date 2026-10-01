<?php

declare(strict_types=1);

/**
 * @file
 * Installs a disposable SQLite site for page controller wiring checks.
 *
 * Run only in an empty container with a source copy at /app, no site mounts,
 * and XINSHI_API_ISOLATED_TEST=1. Never point this runner at a real site.
 */

$root = dirname(__DIR__, 5);
if (getenv('XINSHI_API_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') ||
    $root !== '/app' || file_exists($root . '/docroot/sites/default/settings.php') ||
    file_exists('/tmp/xinshi-api-test')) {
  throw new RuntimeException('Use a fresh, isolated test container with a source copy at /app.');
}
require __DIR__ . '/bootstrap.php';
require_once $root . '/docroot/core/includes/bootstrap.inc';
chdir($root . '/docroot');
mkdir('sites/default/files', 0777, TRUE);
mkdir('/tmp/xinshi-api-test');
file_put_contents('sites/default/settings.php', <<<'PHP'
<?php
$databases['default']['default'] = [
  'driver' => 'sqlite', 'database' => '/tmp/xinshi-api-test/site.sqlite',
  'namespace' => 'Drupal\sqlite\Driver\Database\sqlite',
  'autoload' => 'core/modules/sqlite/src/Driver/Database/sqlite/', 'prefix' => '',
];
$settings['hash_salt'] = 'isolated-api-integration-tests-only';
$settings['config_sync_directory'] = '/tmp/xinshi-api-test/config';
$settings['skip_permissions_hardening'] = TRUE;
PHP);
file_put_contents('sites/default/default.settings.php', '<?php');
file_put_contents('sites/default/default.services.yml', 'parameters: {}');
$_SERVER += [
  'HTTP_HOST' => 'api-test', 'SERVER_NAME' => 'api-test', 'SERVER_PORT' => 80,
  'REQUEST_URI' => '/', 'SCRIPT_NAME' => '/index.php', 'SCRIPT_FILENAME' => $root . '/docroot/index.php',
  'REQUEST_METHOD' => 'GET', 'SERVER_PROTOCOL' => 'HTTP/1.1', 'HTTP_USER_AGENT' => 'api-tests',
];
require_once $root . '/docroot/core/includes/install.core.inc';
install_drupal($loader, [
  'parameters' => ['profile' => 'minimal', 'langcode' => 'en'],
  'site_path' => 'sites/default',
  'forms' => ['install_configure_form' => [
    'site_name' => 'API integration tests', 'site_mail' => 'test@example.test',
    'account' => ['name' => 'test-admin', 'mail' => 'test@example.test',
      'pass' => ['pass1' => 'test-only-password', 'pass2' => 'test-only-password']],
    'enable_update_status_module' => FALSE, 'enable_update_status_emails' => FALSE,
  ]],
]);
\Drupal::service('module_installer')->install(['xinshi_api']);

function check(bool $condition, string $description): void {
  if (!$condition) {
    throw new RuntimeException($description);
  }
  echo 'PASS: ' . $description . "\n";
}

$container = \Drupal::getContainer();
check($container->get('xinshi_api.page_json_response') instanceof \Drupal\xinshi_api\PageJsonResponseBuilder,
  'Page response service constructs from the installed module definition');
check($container->get('xinshi_api.page_json_cache') instanceof \Drupal\Core\Cache\VariationCacheInterface,
  'Page variation cache constructs from the real core factory');
$route = $container->get('router.route_provider')->getRouteByName('xinshi_api.v3.landingPage');
$definition = $route->getDefault('_controller');
$resolver = $container->get('controller_resolver');
$controller = $resolver->getControllerFromDefinition($definition, '/api/v3/landingPage');
check(is_callable($controller), 'Installed landing page route resolves to a callable');
check($controller[0] instanceof \Drupal\xinshi_api\Controller\LandingPageController && $controller[1] === 'landingPage',
  'Route keeps the existing landingPage controller and method');

// A stale pre-service container reproduces the public error and its cause.
$stale = new \Drupal\Core\DependencyInjection\ContainerBuilder();
$staleResolver = new \Drupal\Core\Controller\ControllerResolver(
  new \Drupal\Core\Utility\CallableResolver(new \Drupal\Core\DependencyInjection\ClassResolver($stale)));
try {
  $staleResolver->getControllerFromDefinition($definition, '/api/v3/landingPage');
  throw new LogicException('Missing page response service must fail controller resolution.');
}
catch (InvalidArgumentException $exception) {
  check($exception->getMessage() === 'The controller for URI "/api/v3/landingPage" is not callable.',
    'Missing service reproduces the reported outer exception');
  check($exception->getPrevious() instanceof \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
    && str_contains($exception->getPrevious()->getMessage(), 'xinshi_api.page_json_response'),
    'Previous exception identifies the missing page response service');
}
// Exercise the actual draft service and routes with optional moderation off/on.
\Drupal\node\Entity\NodeType::create(['type' => 'landing_page', 'name' => 'Landing page'])->save();
$limited = \Drupal\user\Entity\User::create(['name' => 'draft-wiring-reader', 'status' => 1]);
$limited->save();
foreach ([FALSE, TRUE] as $moderated) {
  if ($moderated) {
    \Drupal::service('module_installer')->install(['content_moderation']);
  }
  $container = \Drupal::getContainer();
  $phase = $moderated ? 'moderation enabled' : 'moderation disabled';
  check(\Drupal::moduleHandler()->moduleExists('content_moderation') === $moderated, $phase);
  foreach ([
    'page_moderation_policy' => \Drupal\xinshi_api\PageModerationPolicy::class,
    'page_writer' => \Drupal\xinshi_api\PageWriteService::class,
    'page_drafts' => \Drupal\xinshi_api\PageDraftService::class,
  ] as $id => $class) {
    check($container->get('xinshi_api.' . $id) instanceof $class, $id . ' constructs with ' . $phase);
  }
  foreach (['capabilities', 'create', 'read', 'change', 'find'] as $suffix) {
    $route = $container->get('router.route_provider')->getRouteByName('xinshi_api.v3.landingPage.drafts.' . $suffix);
    $draftController = $container->get('controller_resolver')->getControllerFromDefinition(
      $route->getDefault('_controller'), $route->getPath());
    check(is_callable($draftController), 'Draft route resolves: ' . $suffix . ', ' . $phase);
    if ($suffix === 'capabilities') {
      $capabilitiesController = $draftController;
    }
  }
  $switcher = $container->get('account_switcher');
  $switcher->switchTo($limited);
  try {
    $response = $capabilitiesController();
    $data = json_decode($response->getContent(), TRUE, 512, JSON_THROW_ON_ERROR);
    check($response->getStatusCode() === 200 && $data === ['uid' => (string) $limited->id(), 'permissions' => []],
      'Limited authenticated account receives its own uid and no draft permissions, ' . $phase);
    check(str_contains($response->headers->get('Cache-Control'), 'no-store'),
      'Capabilities remain uncacheable, ' . $phase);
  }
  finally {
    $switcher->switchBack();
  }
  foreach (['node', 'block_content', 'xinshi_page_draft_operation'] as $table) {
    check((int) \Drupal::database()->select($table)->countQuery()->execute()->fetchField() === 0,
      'Capability lookup leaves ' . $table . ' empty, ' . $phase);
  }
}

// Reproduce the old eight-argument container definition against the new class.
$oldArguments = array_map($container->get(...), [
  'database', 'entity_type.manager', 'current_user', 'language_manager', 'datetime.time', 'uuid',
  'content_moderation.moderation_information', 'content_moderation.state_transition_validation',
]);
$oldDefinition = [
  'machine_format' => TRUE,
  'services' => ['xinshi_api.page_drafts' => [
    'class' => \Drupal\xinshi_api\PageDraftService::class,
    'arguments' => $oldArguments,
  ]],
];
try {
  (new \Drupal\Component\DependencyInjection\Container($oldDefinition))->get('xinshi_api.page_drafts');
  throw new LogicException('The old draft definition must fail with the new constructor.');
}
catch (TypeError $exception) {
  check(str_contains($exception->getMessage(), 'Argument #7 ($moderationPolicy)')
    && str_contains($exception->getMessage(), \Drupal\content_moderation\ModerationInformation::class),
    'Old container definition reproduces the reported draft constructor TypeError');
}
$oldDefinition['services']['xinshi_api.page_drafts']['arguments'] = [
  ...array_slice($oldArguments, 0, 6), $container->get('xinshi_api.page_moderation_policy'),
];
check((new \Drupal\Component\DependencyInjection\Container($oldDefinition))->get('xinshi_api.page_drafts')
  instanceof \Drupal\xinshi_api\PageDraftService,
  'Replacing the old arguments with the current definition restores construction');
echo "Isolated API controller wiring checks passed.\n";


// Simple OAuth is optional for the API module. Verify both container states,
// then exercise persisted users/roles/scopes through the real permission chain.
check(!\Drupal::getContainer()->has('xinshi_api.oauth_permission_cache_policy'),
  'No OAuth cache policy is registered while Simple OAuth is disabled');
\Drupal::service('module_installer')->install(['simple_oauth']);
$container = \Drupal::getContainer();
check($container->get('xinshi_api.oauth_permission_cache_policy') instanceof \Drupal\xinshi_api\Access\OAuthPermissionCachePolicy,
  'Enabling Simple OAuth registers the permission cache policy');
check($container->get('cache_context.xinshi_oauth_account') instanceof \Drupal\xinshi_api\Cache\Context\OAuthAccountCacheContext,
  'OAuth account context constructs from the rebuilt container');
check(in_array('xinshi_oauth_account', $container->get('cache_contexts_manager')->getAll(), TRUE),
  'OAuth account context is available to the real cache context manager');

\Drupal\user\Entity\Role::create([
  'id' => 'cache_webmaster', 'label' => 'Cache fixture webmaster', 'permissions' => ['access content'],
])->save();
\Drupal\user\Entity\Role::create([
  'id' => 'cache_admin', 'label' => 'Cache fixture administrator', 'is_admin' => TRUE,
])->save();
$scope = \Drupal\simple_oauth\Entity\Oauth2Scope::create([
  'name' => 'cache_webmaster', 'granularity_id' => 'role',
  'granularity_configuration' => ['role' => 'cache_webmaster'],
  'grant_types' => ['authorization_code' => ['status' => TRUE]],
]);
$scope->save();
$consumer = \Drupal\consumers\Entity\Consumer::create([
  'label' => 'Permission cache fixture', 'client_id' => 'permission-cache-fixture',
]);
$consumer->save();
$accounts = [];
foreach (['ordinary' => FALSE, 'admin' => TRUE] as $name => $admin) {
  $user = \Drupal\user\Entity\User::create([
    'name' => 'cache-fixture-' . $name, 'status' => 1,
    'roles' => $admin ? ['cache_webmaster', 'cache_admin'] : ['cache_webmaster'],
  ]);
  $user->save();
  check((int) $user->id() !== 1, 'Fixture does not rely on uid 1: ' . $name);
  // No JWT is issued here: this test starts at the authenticated account
  // boundary, with real field resolution for the stored user, scope and client.
  $token = \Drupal\simple_oauth\Entity\Oauth2Token::create([
    'bundle' => 'access_token', 'auth_user_id' => $user->id(),
    'client' => $consumer->id(), 'scopes' => [['scope_id' => $scope->id()]],
  ]);
  $accounts[$name] = new \Drupal\simple_oauth\Authentication\TokenAuthUser(
    $container->get('permission_checker'), $token,
    $container->get('psr7.http_message_factory'), $container->get('request_stack'),
  );
}
check($accounts['ordinary']->getRoles() === $accounts['admin']->getRoles(),
  'OAuth exposes identical scope-filtered roles for different underlying accounts');
$permissionCheck = new \Drupal\user\Access\PermissionAccessCheck();
foreach ([['ordinary', 'admin'], ['admin', 'ordinary']] as $order) {
  $container->get('cache_tags.invalidator')->invalidateTags(['access_policies']);
  foreach ($order as $name) {
    $account = $accounts[$name];
    $container->get('current_user')->setAccount($account);
    foreach (['administer xinshi_ai', 'view site ai usage', 'view site ai usage,view ai supplier costs'] as $permission) {
      $route = new \Symfony\Component\Routing\Route('/fixture', [], ['_permission' => $permission]);
      check($permissionCheck->access($route, $account)->isAllowed() === ($name === 'admin'),
        'Installed permission pipeline isolates ' . $name . ' for ' . $permission . ' after ' . $order[0]);
    }
  }
}
echo "Isolated OAuth permission cache checks passed.\n";
