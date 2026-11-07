<?php

declare(strict_types=1);

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Database\Database;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\YamlFileLoader;
use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Core\Site\Settings;
use Drupal\xinshi_credits\XinshiCreditsServiceProvider;
use Drupal\xinshi_ai_usage\XinshiAiUsageServiceProvider;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__, 5);
if (getenv('XINSHI_FINANCE_ISOLATED_TEST') !== '1'
  || !str_starts_with(realpath($root), realpath(sys_get_temp_dir()) . '/')
  || !is_file($root . '/.isolated-finance-fixture') || is_dir($root . '/docroot/sites')) {
  throw new RuntimeException('Use run-finance-isolated.sh with a disposable source copy.');
}
$loader = require $root . '/vendor/autoload.php';
$loader->addPsr4('Drupal\\Core\\', $root . '/docroot/core/lib/Drupal/Core');
$loader->addPsr4('Drupal\\Component\\', $root . '/docroot/core/lib/Drupal/Component');
$loader->addPsr4('Drupal\\sqlite\\', $root . '/docroot/core/modules/sqlite/src');
require_once $root . '/docroot/core/lib/Drupal.php';
$modules = ['xinshi_ai_usage', 'xinshi_credits', 'xinshi_rewards', 'xinshi_ai_billing', 'xinshi_ai'];
foreach ($modules as $module) $loader->addPsr4('Drupal\\' . $module . '\\', $root . '/docroot/modules/custom/' . $module . '/src');
Database::addConnectionInfo('default', 'default', [
  'driver' => 'sqlite', 'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
  'database' => ':memory:', 'prefix' => '',
]);
$db = Database::getConnection();
$db->query('CREATE TABLE credit_reservation (state TEXT)');
$db->query('CREATE TABLE ai_billing_authorization (state TEXT)');
$checks = 0;
function check(bool $value, string $message): void {
  if (!$value) throw new RuntimeException($message);
  $GLOBALS['checks']++;
  echo 'PASS: ' . $message . PHP_EOL;
}

/** Load the same module YAML and providers that Drupal loads during a rebuild. */
function assemble(array $configuration, array $overrides = [], ?array $modules = NULL): array {
  global $root;
  $modules ??= $GLOBALS['modules'];
  foreach (['xinshi_credits.settings', 'xinshi_ai_billing.settings', 'xinshi_rewards.settings'] as $name) {
    file_put_contents($root . '/config/' . $name . '.yml', Yaml::dump($configuration[$name] ?? []));
  }
  // FileStorage caches content by mtime; independent instances use a fresh file cache prefix.
  \Drupal\Component\FileCache\FileCacheFactory::setPrefix(bin2hex(random_bytes(8)));
  new Settings(['bootstrap_config_storage' => static fn() => new FileStorage($root . '/config'),
    'xinshi_finance' => $overrides]);
  $container = new ContainerBuilder();
  $container->setParameter('container.modules', array_fill_keys($modules, []));
  $container->setParameter('cors.config', []);
  (new XinshiAiUsageServiceProvider())->register($container);
  $yaml = new YamlFileLoader($container);
  foreach ($modules as $module) {
    if ($module !== 'xinshi_ai') $yaml->load($root . '/docroot/modules/custom/' . $module . '/' . $module . '.services.yml');
  }
  if (in_array('xinshi_credits', $modules, TRUE)) (new XinshiCreditsServiceProvider())->alter($container);
  $routes = new RouteCollection();
  foreach ($modules as $module) {
    foreach (Yaml::parseFile($root . '/docroot/modules/custom/' . $module . '/' . $module . '.routing.yml') as $name => $definition) {
      $routes->add($name, new Route($definition['path'], $definition['defaults'] ?? [],
        $definition['requirements'] ?? [], $definition['options'] ?? [], '', [], $definition['methods'] ?? []));
    }
  }
  $event = new RouteBuildEvent($routes);
  $container->get('xinshi_ai_usage.finance_routes')->onAlterRoutes($event);
  if ($container->has('xinshi_credits.routes')) $container->get('xinshi_credits.routes')->onAlterRoutes($event);
  // External Drupal services are synthetic; optional missing references must still resolve to NULL.
  $container->register('logger.channel_base', stdClass::class)->setAbstract(TRUE);
  $external = static function (mixed $value) use (&$external, $container): void {
    if ($value instanceof Reference && $value->getInvalidBehavior() === 1 && !$container->has((string) $value)) {
      $container->register((string) $value)->setSynthetic(TRUE)->setPublic(TRUE);
    } elseif (is_array($value)) foreach ($value as $item) $external($item);
  };
  foreach ($container->getDefinitions() as $definition) $external($definition->getArguments());
  $container->compile();
  return [$container, $routes];
}

foreach ([['xinshi_ai_usage', 'xinshi_ai'], $modules] as $installed) {
  [$container, $routes] = assemble([], [], $installed);
  check($container->getParameter('xinshi_site_finance') == [
    'version' => 1, 'credits' => FALSE, 'chat_billing' => FALSE, 'image_billing' => FALSE,
    'billing_recovery' => FALSE,
  ], 'Disabled and absent financial modules publish the same explicit snapshot');
  foreach (['xinshi_credits.ledger', 'xinshi_credits.expiry', 'xinshi_rewards.rewards', 'xinshi_ai_billing.billing',
    'xinshi_ai_billing.images', 'xinshi_ai_billing.reports'] as $service) check(!$container->has($service), 'No disabled service: ' . $service);
  check($container->has('xinshi_ai_usage.report') && $container->has('xinshi_ai_usage.site_report'), 'Personal and site usage reports remain assembled');
  check($container->getDefinition('xinshi_ai_usage.report')->getArgument(2) === NULL, 'Usage reports have no disabled charge adapter');
  foreach (['/api/v3/credits/me/account', '/api/v3/credits/ai/quotes', '/api/v3/image-jobs/quote'] as $path) {
    try { (new UrlMatcher($routes, new RequestContext($path)))->match($path); throw new RuntimeException('Disabled route matched'); }
    catch (\Symfony\Component\Routing\Exception\ResourceNotFoundException) { check(TRUE, 'No disabled route: ' . $path); }
  }
  check($routes->get('xinshi_ai.image_job.create') !== NULL, 'Uncharged image creation remains registered');
  $response = \Drupal\xinshi_ai_usage\Controller\SiteFinanceController::create($container)->get();
  check($response->getStatusCode() === 200 && str_contains($response->headers->get('Cache-Control'), 'no-store'), 'Public snapshot is a lightweight uncached response');
  if ($container->has('xinshi_credits.uninstall')) {
    \Drupal::setContainer($container);
    require_once $root . '/docroot/modules/custom/xinshi_credits/xinshi_credits.module';
    require_once $root . '/docroot/modules/custom/xinshi_ai_billing/xinshi_ai_billing.module';
    xinshi_credits_cron(); xinshi_ai_billing_cron();
    check(TRUE, 'Disabled cron does not request removed services');
  }
}
$credits = ['xinshi_credits.settings' => ['grants_enabled' => TRUE]];
[$container, $routes] = assemble($credits);
check($container->has('xinshi_credits.ledger') && !$container->has('xinshi_ai_billing.billing'), 'Credits operate independently of AI charging');
check($routes->get('xinshi_credits.account') !== NULL && $routes->get('xinshi_ai_billing.quote') === NULL, 'Credits-only routes exclude AI quotations');
$all = $credits + ['xinshi_ai_billing.settings' => ['enabled' => TRUE, 'image_enabled' => TRUE],
  'xinshi_rewards.settings' => ['daily_enabled' => TRUE]];
[$container, $routes] = assemble($all);
check($container->has('xinshi_ai_billing.images') && $container->has('xinshi_rewards.rewards'), 'Enabled services remain available');
check($routes->get('xinshi_ai.image_job.quote') !== NULL && $routes->get('xinshi_ai_billing.finalize') !== NULL, 'Enabled quotations and signed settlement remain available');
check($container->getDefinition('xinshi_ai_billing.billing')->getArgument(1) === TRUE
  && $container->getParameterBag()->resolveValue($container->getDefinition('xinshi_ai_billing.images')->getArgument(8))['image_billing'] === TRUE,
  'Billing adapters receive the same compiled snapshot as the public response');
assemble([]);
check($container->getParameter('xinshi_site_finance')['chat_billing'] === TRUE,
  'Stored configuration changes cannot mutate an already compiled host');
$reflection = new ReflectionClass(\Drupal\xinshi_ai_billing\ImageBilling::class);
$images = $reflection->newInstanceWithoutConstructor();
$reflection->getProperty('finance')->setValue($images, $container->getParameter('xinshi_site_finance'));
check($images->exemption('fixture') === NULL, 'Image admission still requires charging after a saved configuration change');
$off = $reflection->newInstanceWithoutConstructor();
$reflection->getProperty('finance')->setValue($off, ['chat_billing' => FALSE, 'image_billing' => FALSE]);
check($off->exemption('fixture') === 'disabled', 'A disabled image adapter requires no financial dependencies for exemption');
$GLOBALS['config']['xinshi_ai_billing.settings'] = ['enabled' => TRUE];
[$overridden] = assemble($credits);
check($overridden->getParameter('xinshi_site_finance')['chat_billing'] === TRUE, 'Site configuration overrides participate in assembly');
unset($GLOBALS['config']['xinshi_ai_billing.settings']);
$db->insert('credit_reservation')->fields(['state' => 'held'])->execute();
$db->insert('ai_billing_authorization')->fields(['state' => 'open'])->execute();
[$container, $routes] = assemble([]);
check($container->has('xinshi_ai_billing.billing') && $container->has('xinshi_credits.lifecycle'), 'Operational shutdown retains pending settlement services');
check($container->getParameter('xinshi_site_finance')['chat_billing'] === FALSE, 'Recovery does not advertise new quotations');
check($routes->get('xinshi_ai_billing.quote') === NULL && $routes->get('xinshi_ai_billing.finalize') !== NULL,
  'Recovery keeps settlement routes without registering new quote routes');
try { assemble([], ['credits' => FALSE, 'billing' => FALSE]); throw new RuntimeException('Pending shutdown accepted'); }
catch (LogicException) { check(TRUE, 'Full removal refuses pending financial work'); }
check($db->select('credit_reservation', 'r')->fields('r', ['state'])->execute()->fetchField() === 'held', 'Assembly never releases unknown holds');
$db->update('credit_reservation')->fields(['state' => 'settled'])->execute();
$db->update('ai_billing_authorization')->fields(['state' => 'closed'])->execute();
[$container] = assemble([]);
check(!$container->has('xinshi_ai_billing.billing'), 'Closed history does not keep billing loaded');
check((int) $db->select('ai_billing_authorization')->countQuery()->execute()->fetchField() === 1, 'Closed financial history is retained');
foreach ([['credits' => 'false'], ['billing' => TRUE, 'credits' => FALSE], ['unknown' => TRUE]] as $invalid) {
  try { assemble([], $invalid); throw new RuntimeException('Invalid configuration accepted'); }
  catch (LogicException) { check(TRUE, 'Invalid assembly fails explicitly'); }
}
try { assemble(['xinshi_ai_billing.settings' => ['enabled' => 'false']]); throw new RuntimeException('Invalid flag accepted'); }
catch (LogicException) { check(TRUE, 'Malformed operational flags never become free-mode defaults'); }
echo 'Finance assembly checks: ' . $checks . PHP_EOL;
