<?php

/**
 * @file
 * Tests real routing, configuration, encrypted storage and upgrade with synthetic credentials.
 */

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Site\Settings;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\xinshi_ai\Controller\SupplierConfigurationController;
use Drupal\xinshi_ai\Form\SettingsForm;
use Drupal\xinshi_ai\Service\SupplierConfiguration;
use Drupal\xinshi_ai_runtime\Service\ServiceIdentity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__, 6);
if (getenv('XINSHI_SUPPLIER_ISOLATED_TEST') !== '1' ||
  !str_starts_with(basename($root), 'xinshi-supplier-tests-') || !is_file($root . '/.supplier-fixture')) {
  throw new RuntimeException('Use the supplier source-copy runner.');
}
$complete = FALSE;
register_shutdown_function(static function () use (&$complete): void { if (!$complete) exit(1); });
require $root . '/vendor/autoload.php';
$module = dirname(__DIR__, 2);
$install = file_get_contents($module . '/xinshi_ai.install');
$defaults = file_get_contents($module . '/config/install/xinshi_ai.settings.yml');
$routes = Yaml::parseFile($module . '/xinshi_ai.routing.yml');
$services = Yaml::parseFile($module . '/xinshi_ai.services.yml')['services'];
rename($module . '/config/install', $module . '/config/fixture-unused');
mkdir($module . '/config/install');
file_put_contents($module . '/config/install/xinshi_ai.settings.yml', $defaults);
file_put_contents($module . '/xinshi_ai.info.yml', Yaml::dump([
  'name' => 'Supplier configuration fixture', 'type' => 'module', 'core_version_requirement' => '^11',
  'dependencies' => ['drupal:user', 'xinshi_ai_runtime:xinshi_ai_runtime'],
]));
file_put_contents($module . '/xinshi_ai.module', '<?php');
file_put_contents($module . '/xinshi_ai.install', '<?php');
file_put_contents($module . '/xinshi_ai.routing.yml', Yaml::dump(array_intersect_key($routes,
  array_flip(['xinshi_ai.settings', 'xinshi_ai.suppliers', 'xinshi_ai.models.list'])), 8));
file_put_contents($module . '/xinshi_ai.services.yml', Yaml::dump(['services' => array_intersect_key($services,
  array_flip(['xinshi_ai.supplier_keys', 'xinshi_ai.supplier_configuration', 'xinshi_ai.model_registry',
    'xinshi_ai.web_search_credentials', 'xinshi_ai.figma_vault']))], 8));
require __DIR__ . '/install.php';
\Drupal::service('module_installer')->install(['xinshi_ai']);
file_put_contents($root . '/supplier-install.php', $install);
require $root . '/supplier-install.php';
\Drupal::getContainer()->set('xinshi_ai.product_documents', new class {
  public function availableContentTypes(): array { return []; }
});
$checks = 0;
function check(bool $condition, string $message): void {
  if (!$condition) throw new RuntimeException($message);
  $GLOBALS['checks']++;
  echo 'PASS: ' . $message . PHP_EOL;
}
function signed(string $path = '/api/v3/ai/service/suppliers', string $secret = 'synthetic-service-secret',
  ?string $nonce = NULL, ?string $timestamp = NULL, string $body = ''): Request {
  $request = Request::create($path, 'GET', [], [], [], [], $body);
  $timestamp ??= (string) time();
  $nonce ??= bin2hex(random_bytes(16));
  $request->headers->set(ServiceIdentity::HEADER_PRODUCER, 'chat-node');
  $request->headers->set(ServiceIdentity::HEADER_TIMESTAMP, $timestamp);
  $request->headers->set(ServiceIdentity::HEADER_NONCE, $nonce);
  $request->headers->set(ServiceIdentity::HEADER_SIGNATURE, ServiceIdentity::sign(
    $secret, 'chat-node', $timestamp, $nonce, 'GET', $request->getPathInfo(), $body));
  return $request;
}
$factory = \Drupal::configFactory();
$vault = \Drupal::service('xinshi_ai.supplier_keys');
$suppliers = \Drupal::service('xinshi_ai.supplier_configuration');
$controller = SupplierConfigurationController::create(\Drupal::getContainer());
check(!\Drupal::moduleHandler()->moduleExists('xinshi_ai_usage'), 'Service channel works without metering.');
check($factory->get('xinshi_ai.settings')->get('gateway.api_key') === NULL, 'New installs have no exportable key field.');
$factory->getEditable('xinshi_ai.settings')->set('gateway.base_url', 'https://gateway.test')
  ->set('gateway.api_key', 'synthetic-legacy-key')->set('fixture_preserve', 'keep')->save();
xinshi_ai_update_10010();
check($vault->get('xinshi') === 'synthetic-legacy-key', 'Upgrade transfers the legacy key to encrypted storage.');
check($factory->get('xinshi_ai.settings')->get('gateway.api_key') === NULL, 'Upgrade removes the plaintext configuration key.');
$vault->set('synthetic-rotated-key', 'xinshi');
xinshi_ai_update_10010();
check($vault->get('xinshi') === 'synthetic-rotated-key', 'Repeated upgrade preserves a newer key.');
$factory->getEditable('xinshi_ai.settings')->set('gateway.api_key', 'synthetic-unmigrated-key')->save();
\Drupal::keyValue('xinshi_ai.suppliers')->set('xinshi', 'corrupt');
try {
  xinshi_ai_update_10010();
  throw new RuntimeException('Expected migration refusal');
}
catch (RuntimeException $error) {
  check($factory->getEditable('xinshi_ai.settings')->get('gateway.api_key') === 'synthetic-unmigrated-key',
    'Failed decryption leaves the legacy migration source intact.');
}
$vault->set('synthetic-rotated-key', 'xinshi');
xinshi_ai_update_10010();
$form = SettingsForm::create(\Drupal::getContainer());
$built = $form->buildForm([], new FormState());
check($built['gateway']['api_key']['#type'] === 'password' && !isset($built['gateway']['api_key']['#default_value']), 'Form never fills saved supplier keys.');
$values = ['base_url' => 'https://gateway.test', 'api_key' => '', 'request_timeout' => 180,
  'aliyun' => ['base_url' => 'https://aliyun.test/compatible-mode/v1', 'api_key' => 'synthetic-aliyun-key'],
  'harness' => ['service' => ['url' => '', 'producer_id' => 'chat-node']]];
$state = (new FormState())->setValues($values);
$form->validateForm($built, $state);
check($state->getErrors() === [], 'Valid supplier settings pass form validation.');
$form->submitForm($built, $state);
check($vault->get('xinshi') === 'synthetic-rotated-key', 'Empty password retains the gateway key.');
check($vault->get('aliyun') === 'synthetic-aliyun-key', 'Aliyun uses the separate encrypted supplier entry.');
$export = new FileStorage($root . '/fixture-data/export');
$export->write('xinshi_ai.settings', $factory->getEditable('xinshi_ai.settings')->getRawData());
$exported = file_get_contents($root . '/fixture-data/export/xinshi_ai.settings.yml');
check(!str_contains($exported, 'synthetic-') && !str_contains($exported, 'api_key'), 'Configuration export contains no supplier key.');
check(!str_contains(json_encode(\Drupal::keyValue('xinshi_ai.suppliers')->getAll()), 'synthetic-'), 'Database key-value rows contain ciphertext.');
$anonymous = $controller->read(Request::create('/api/v3/ai/service/suppliers'));
check($anonymous->getStatusCode() === 401 && $anonymous->headers->hasCacheControlDirective('no-store'), 'Anonymous denial is not cacheable.');
$request = signed();
$reply = $controller->read($request);
$payload = json_decode($reply->getContent(), TRUE);
check($reply->getStatusCode() === 200 && $reply->headers->hasCacheControlDirective('no-store'), 'Valid independent signature reads the server-only snapshot.');
check($payload['platforms']['xinshi']['baseURL'] === 'https://gateway.test/v1' &&
  $payload['platforms']['aliyun']['account'] === ['ref' => 'aliyun', 'payer' => 'platform'], 'Endpoints and payer travel with each platform key.');
check($controller->read($request)->getStatusCode() === 401, 'A used nonce cannot be replayed.');
check($controller->read(signed(secret: 'wrong-secret'))->getStatusCode() === 401, 'Invalid signatures cannot read credentials.');
check($controller->read(signed(timestamp: (string) (time() - 301)))->getStatusCode() === 401, 'Expired signatures cannot read credentials.');
foreach (['Cookie' => 'synthetic-session', 'Authorization' => 'Bearer synthetic-oauth'] as $header => $value) {
  $userRequest = Request::create('/api/v3/ai/service/suppliers');
  $userRequest->headers->set($header, $value);
  check($controller->read($userRequest)->getStatusCode() === 401, 'User headers do not substitute for a service signature.');
}
check($controller->read(signed('/api/v3/ai/service/suppliers?extra=1'))->getStatusCode() === 400, 'Queries are rejected instead of changing the signed resource.');
$foreign = signed('/api/v3/ai/models');
$foreign->server->set('REQUEST_URI', '/api/v3/ai/service/suppliers');
$foreign = Request::create('/api/v3/ai/service/suppliers', 'GET', [], [], [], $foreign->server->all());
$foreign->headers->replace(signed('/api/v3/ai/models')->headers->all());
check($controller->read($foreign)->getStatusCode() === 401, 'A signature for another path grants no supplier access.');
$router = \Drupal::service('router.route_provider')->getRouteByName('xinshi_ai.suppliers');
check($router->getOption('_auth') === [] && $router->getOption('no_cache') === TRUE, 'Real Drupal route disables user authentication and page caching.');
check(!\Drupal::service('access_manager')->checkNamedRoute('xinshi_ai.settings', [], new AnonymousUserSession()), 'Anonymous users cannot change supplier settings.');
$role = Role::create(['id' => 'supplier_admin', 'label' => 'Supplier admin']);
$role->grantPermission('administer xinshi_ai')->save();
$admin = User::create(['name' => 'supplier-admin', 'status' => 1, 'roles' => ['supplier_admin']]);
$admin->save();
check(\Drupal::service('access_manager')->checkNamedRoute('xinshi_ai.settings', [], $admin), 'The existing AI administrator permission allows supplier configuration.');
$kernel = \Drupal::service('http_kernel');
$http = $kernel->handle(Request::create('/api/v3/ai/service/suppliers', 'GET', [], [], [], ['HTTP_HOST' => 'supplier.test']));
check($http->getStatusCode() === 401 && $http->headers->hasCacheControlDirective('no-store'), 'Real anonymous HTTP dispatch cannot read supplier secrets.');
$httpSigned = $kernel->handle(signed());
check($httpSigned->getStatusCode() === 200 && $httpSigned->headers->hasCacheControlDirective('no-store'), 'Real signed HTTP dispatch reads the private supplier snapshot.');
Role::load('anonymous')->grantPermission('access content')->save();
$factory->getEditable('xinshi_ai.models')->set('platforms', [
  'xinshi' => ['label' => 'Gateway', 'enabled' => TRUE, 'api_key' => 'synthetic-rotated-key', 'base_url' => 'https://gateway.test'],
])->set('models', [])->save();
$public = $kernel->handle(Request::create('/api/v3/ai/models', 'GET', [], [], [], ['HTTP_HOST' => 'supplier.test']));
check($public->getStatusCode() === 200 && !str_contains($public->getContent(), 'synthetic-rotated-key') &&
  !str_contains($public->getContent(), 'synthetic-aliyun-key'), 'Successful public model HTTP responses contain no supplier keys.');
$settings = Settings::getAll();
$overrides = $settings;
$overrides['xinshi_ai.supplier_keys'] = ['xinshi' => 'synthetic-deployment-key', 'aliyun' => ''];
$overridden = new SupplierConfiguration($factory, $vault, new Settings($overrides));
check($overridden->apiKey('xinshi') === 'synthetic-deployment-key' && !$overridden->hasKey('aliyun'), 'Deployment overrides win and an empty override disables a platform.');
new Settings($settings);
\Drupal::keyValue('xinshi_ai.suppliers')->set('xinshi', 'corrupt');
$failed = $controller->read(signed());
check($failed->getStatusCode() === 503 && !str_contains($failed->getContent(), 'key'), 'Unreadable keys produce a sanitized failure.');
$vault->set('synthetic-recovered-key', 'xinshi');
check($controller->read(signed())->getStatusCode() === 200, 'A repaired key restores service access.');
$state = (new FormState())->setValues(array_replace($values, ['clear_gateway_key' => TRUE,
  'aliyun' => ['base_url' => '', 'api_key' => '', 'clear_key' => TRUE]]));
$form->submitForm($built, $state);
check(!$vault->has('xinshi') && !$vault->has('aliyun'), 'Explicit clears remove both stored keys.');
echo "Supplier configuration: $checks checks passed.\n";
$complete = TRUE;
