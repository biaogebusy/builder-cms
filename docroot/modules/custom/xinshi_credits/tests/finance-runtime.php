<?php

declare(strict_types=1);

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DrupalKernel;
use Drupal\user\Entity\{Role, User};
use Drupal\xinshi_ai_usage\Service\ProducerIdentity;
use Symfony\Component\HttpFoundation\Request;

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures.php';
set_exception_handler(static function (Throwable $error): never { fwrite(STDERR, (string) $error . PHP_EOL); exit(1); });
if (!is_file($fixture . '/finance-settings.php')) throw new RuntimeException('Use the finance-only isolated installation.');

// A separate PHP process compiles and caches the real container before serving requests.
if (($argv[1] ?? '') === 'rebuild') {
  $kernel->invalidateContainer();
  $kernel->shutdown();
  $kernel = DrupalKernel::createFromRequest($request, $loader, 'prod');
  $kernel->boot(); $kernel->preHandle($request);
  \Drupal::service('router.builder')->rebuild();
  exit(0);
}

/** Rebuild from settings on disk, then reload the parent from the compiled definition. */
function financeRestart(array $assembly = [], bool $success = TRUE, string $mode = 'enforce'): void {
  global $fixture, $kernel, $loader, $request;
  file_put_contents($fixture . '/finance-settings.php', "<?php\n\$settings['xinshi_finance'] = " . var_export($assembly, TRUE) . ";\n"
    . "\$settings['xinshi_ai_usage.mode'] = " . var_export($mode, TRUE) . ";\n"
    . "\$settings['xinshi_ai_usage.producers'] = ['finance-node' => ['site_id' => 'fixture', 'secret' => 'synthetic-finance-only']];\n");
  $process = proc_open([PHP_BINARY, '-d', 'extension=pdo_sqlite', '-d', 'extension=pdo_mysql', __FILE__, 'rebuild'],
    [0 => ['file', '/dev/null', 'r'], 1 => ['file', $fixture . '/finance-rebuild.log', 'w'],
      2 => ['file', $fixture . '/finance-rebuild.log', 'a']], $pipes);
  $status = proc_close($process);
  if (!$success) {
    check($status !== 0 && str_contains(file_get_contents($fixture . '/finance-rebuild.log'), 'LogicException'),
      'Invalid or unsafe assembly is rejected by the real Drupal rebuild');
    return;
  }
  if ($status !== 0) throw new RuntimeException(file_get_contents($fixture . '/finance-rebuild.log'));
  $kernel->shutdown();
  $kernel = DrupalKernel::createFromRequest($request, $loader, 'prod');
  $kernel->boot(); $kernel->preHandle($request);
}

/** Read through the real loopback HTTP server, including authentication and cache headers. */
function financeHttp(string $method, string $path, ?string $jar = NULL, array $body = [], array $headers = []): array {
  $curl = curl_init('http://127.0.0.1:8080' . $path); $cache = '';
  curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', ...$headers],
    CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$cache): int {
      if (str_starts_with(strtolower($line), 'cache-control:')) $cache = strtolower($line);
      return strlen($line);
    }]);
  if ($method !== 'GET') curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
  if ($jar) { curl_setopt($curl, CURLOPT_COOKIEFILE, $jar); curl_setopt($curl, CURLOPT_COOKIEJAR, $jar); }
  $content = curl_exec($curl); $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE); curl_close($curl);
  if ($content === FALSE) throw new RuntimeException('Isolated finance HTTP failed');
  return ['status' => $status, 'data' => json_decode($content, TRUE), 'cache' => $cache];
}

/** Compare public capabilities, instantiated services, actual routes and unchanged usage. */
function financeState(string $label, bool $credits, bool $billing, bool $recovery, bool $images = FALSE): void {
  global $fixture, $owner;
  $expected = ['version' => 2, 'metering' => 'enforce', 'harness' => FALSE, 'credits' => $credits, 'chat_billing' => $billing,
    'image_billing' => $images, 'billing_recovery' => $recovery];
  $response = financeHttp('GET', '/api/v3/ai/site-finance');
  check($response['status'] === 200 && $response['data'] == $expected
    && str_contains($response['cache'], 'no-store'), $label . ': public snapshot matches the active container');
  $stack = \Drupal::service('request_stack');
  $stack->push(Request::create('/api/v3/landingPage?content=/core/base'));
  try {
    $data = []; $metadata = new CacheableMetadata();
    \Drupal::moduleHandler()->alter('xinshi_api_data', $data, User::load($owner), $metadata);
    check($data['finance'] == $expected, $label . ': browser configuration uses the same snapshot');
  }
  finally { $stack->pop(); }
  foreach (['xinshi_credits.ledger' => $credits, 'xinshi_ai_billing.billing' => $recovery] as $id => $enabled) {
    check(\Drupal::hasService($id) === $enabled, $label . ': optional service ' . $id);
    if ($enabled) \Drupal::service($id);
  }
  $account = financeHttp('GET', '/api/v3/credits/me/account', $fixture . '/finance-owner.cookie');
  check($account['status'] === ($credits ? 200 : 404), $label . ': account route follows assembly');
  check(financeHttp('POST', '/api/v3/credits/ai/quotes')['status'] === ($billing ? 403 : 404),
    $label . ': quote route follows admission and denies anonymous writes');
  check(financeHttp('POST', '/api/v3/credits/ai/executor/finalize')['status'] === ($recovery ? 403 : 404),
    $label . ': recovery route preserves producer authentication');
  $usage = financeHttp('GET', '/api/v3/ai/usage/me/summary?timezone=UTC', $fixture . '/finance-owner.cookie');
  check($usage['status'] === 200 && $usage['data']['totals']['tokens']['input_tokens_total'] === '321'
    && str_contains($usage['cache'], 'no-store'), $label . ': personal usage remains readable without financial data loss');
  check(financeHttp('GET', '/api/v3/ai/usage/me/summary')['status'] === 403,
    $label . ': anonymous usage is still denied');
  check(financeHttp('GET', '/api/v3/ai/admin/reports/summary?timezone=UTC', $fixture . '/finance-owner.cookie')['status'] === 403,
    $label . ': personal access does not grant site reports');
  $site = financeHttp('GET', '/api/v3/ai/admin/reports/summary?timezone=UTC', $fixture . '/finance-admin.cookie');
  check($site['status'] === 200 && $site['data']['totals']['tokens']['input_tokens_total'] === '321',
    $label . ': authorized site usage remains readable');
}

/** Close a synthetic authorization through the signed recovery endpoint. */
function financeFinalize(array $body): array {
  $path = '/api/v3/credits/ai/executor/finalize'; $timestamp = (string) time(); $nonce = bin2hex(random_bytes(16));
  return financeHttp('POST', $path, body: $body, headers: ['X-Xinshi-Producer: finance-node',
    'X-Xinshi-Timestamp: ' . $timestamp, 'X-Xinshi-Nonce: ' . $nonce,
    'X-Xinshi-Signature: ' . ProducerIdentity::sign('synthetic-finance-only', 'finance-node',
      $timestamp, $nonce, 'POST', $path, json_encode($body))]);
}

Role::create(['id' => 'finance_owner', 'label' => 'Finance owner'])->save();
Role::create(['id' => 'finance_admin', 'label' => 'Usage administrator',
  'permissions' => ['view site ai usage']])->save();
foreach (['owner', 'admin'] as $name) {
  $user = User::create(['name' => 'finance_' . $name, 'status' => 1,
    'pass' => 'synthetic-pass', 'roles' => ['finance_' . $name]]); $user->save();
  if ($name === 'owner') $owner = (string) $user->id();
}
$now = (int) (microtime(TRUE) * 1000) - 2000;
$attempt = ['site_id' => 'fixture', 'attempt_id' => 'finance-usage', 'operation_id' => 'finance-usage',
  'logical_call_id' => 'main', 'attempt_no' => 1, 'producer_id' => 'finance-node',
  'feature' => 'common', 'stage' => 'common', 'payer' => 'platform', 'billing_role' => 'primary',
  'actor_user_id' => $owner, 'provider_account_ref' => 'fixture', 'requested_model' => 'fixture-model',
  'model_id' => 'fixture-model', 'state' => 'succeeded', 'dispatch_state' => 'sent', 'usage_revision' => 1,
  'started_at' => $now, 'finished_at' => $now + 1000, 'bucket_start' => intdiv($now, 3600000) * 3600000,
  'occurred_at' => $now + 1000, 'last_event_id' => 1, 'projection_version' => 1, 'updated_at' => $now + 1000,
  'input_tokens_total' => 321, 'output_tokens_total' => 12, 'usage_quality' => 'reported'];
$db->insert('ai_provider_attempt')->fields($attempt)->execute();
financeRestart();
$server = proc_open([PHP_BINARY, '-d', 'extension=pdo_sqlite', '-d', 'extension=pdo_mysql', '-S', '127.0.0.1:8080',
  __DIR__ . '/http.php'], [0 => ['file', '/dev/null', 'r'], 1 => ['file', $fixture . '/http.log', 'a'],
    2 => ['file', $fixture . '/http.log', 'a']], $pipes);
try {
  for ($i = 0; $i < 60; $i++) {
    if ($socket = @fsockopen('127.0.0.1', 8080, $errno, $error, 0.1)) { fclose($socket); break; }
    usleep(50000);
  }
  foreach (['owner', 'admin'] as $name) {
    check(financeHttp('POST', '/user/login?_format=json', $fixture . '/finance-' . $name . '.cookie',
      ['name' => 'finance_' . $name, 'pass' => 'synthetic-pass'])['status'] === 200, 'Synthetic finance login: ' . $name);
  }
  financeState('Usage-only installation', FALSE, FALSE, FALSE);
  check(!$db->schema()->tableExists('credit_account') && !$db->schema()->tableExists('ai_billing_authorization'),
    'Usage installs without any credit or billing tables');
  foreach ([['credits' => TRUE], ['billing' => TRUE], ['rewards' => TRUE], ['credits' => 'false'], ['unknown' => FALSE]] as $invalid) {
    financeRestart($invalid, FALSE);
    financeRestart();
  }
  financeRestart(['credits' => FALSE, 'billing' => FALSE, 'rewards' => FALSE]);
  check(financeHttp('GET', '/api/v3/ai/site-finance')['data']['credits'] === FALSE,
    'An explicit all-off configuration works without financial modules');
  financeRestart();
  \Drupal::service('module_installer')->install(['xinshi_credits', 'xinshi_rewards', 'xinshi_ai_billing']);
  Role::load('finance_owner')->grantPermission('view own credits')->grantPermission('use ai credits')->save();
  \Drupal::configFactory()->getEditable('xinshi_credits.settings')->set('site_id', 'fixture')->save();
  financeRestart();
  financeState('Installed but disabled', FALSE, FALSE, FALSE);
  financeRestart(['credits' => TRUE]);
  financeState('Credits only', TRUE, FALSE, FALSE);
  check(!\Drupal::hasService('xinshi_rewards.rewards'), 'Credits do not implicitly instantiate rewards');
  \Drupal::configFactory()->getEditable('xinshi_credits.settings')->set('grants_enabled', TRUE)->set('spending_enabled', TRUE)->save();
  \Drupal::configFactory()->getEditable('xinshi_ai_billing.settings')->set('enabled', TRUE)->set('image_enabled', TRUE)->save();
  \Drupal::configFactory()->getEditable('xinshi_rewards.settings')->set('daily_enabled', TRUE)->save();
  financeState('Configuration saved before restart', TRUE, FALSE, FALSE);
  financeRestart();
  financeState('Charging enabled', TRUE, TRUE, TRUE, TRUE);
  check(\Drupal::service('xinshi_rewards.rewards') !== NULL && \Drupal::service('xinshi_ai_billing.images') !== NULL,
    'Real reward and image adapters resolve all their dependencies');
  \Drupal::currentUser()->setAccount(User::load($owner));
  $pool = pool('finance-runtime');
  \Drupal::service('xinshi_credits.ledger')->grant(command($pool, 'finance-runtime', 'finance-gift', $owner, '10000'), 'finance-gift');
  $bounds = json_decode($db->select('credit_reward_pool', 'p')->fields('p', ['bounds_json'])->condition('id', $pool)->execute()->fetchField(), TRUE);
  $profile = ['version' => 'finance-v1', 'feature' => 'text', 'model' => 'fixture-model', 'platform' => 'fixture',
    'category' => 'chat', 'currency' => 'CNY', 'cost_bucket_id' => $bounds['chat']['bucket_id'],
    'evidence_ref' => 'SYNTHETIC ONLY', 'quote_ttl_seconds' => 900, 'calls' => [[
      'model' => 'fixture-model', 'account_ref' => 'fixture', 'role' => 'primary', 'max_calls' => 1,
      'max_input_tokens' => 1000, 'max_output_tokens' => 100, 'max_images' => 0, 'cost_limit_micros' => '500',
      'input_atoms_per_million' => '1000000', 'output_atoms_per_million' => '2000000', 'atoms_per_image' => '0',
    ]]];
  \Drupal::service('xinshi_ai_billing.profiles')->publish($profile);
  \Drupal::configFactory()->getEditable('xinshi_ai_billing.settings')->set('active_versions', ['finance-v1'])->save();
  $billing = \Drupal::service('xinshi_ai_billing.billing');
  $quoteInput = ['feature' => 'text', 'model' => 'fixture-model', 'platform' => 'fixture',
    'request_hash' => str_repeat('a', 64), 'producer_id' => 'finance-node'];
  $quote = $billing->quote($quoteInput, 'finance-pending');
  $accept = ['quote_id' => $quote['quote_id'], 'operation_id' => 'finance-pending', 'request_hash' => $quoteInput['request_hash']];
  $billing->authorize($accept);
  $billing->permit(['producer_id' => 'finance-node', 'site_id' => 'fixture'], ['operation_id' => 'finance-pending',
    'attempt_id' => 'finance-pending', 'model' => 'fixture-model', 'account_ref' => 'fixture', 'role' => 'primary']);
  $before = snapshot();
  foreach (['xinshi_credits.settings' => ['grants_enabled', 'spending_enabled'],
    'xinshi_ai_billing.settings' => ['enabled', 'image_enabled'], 'xinshi_rewards.settings' => ['daily_enabled']] as $name => $flags) {
    $config = \Drupal::configFactory()->getEditable($name);
    foreach ($flags as $flag) $config->set($flag, FALSE);
    $config->save();
  }
  financeRestart();
  financeState('Pending recovery', TRUE, FALSE, TRUE);
  check(snapshot() === $before, 'Rebuilding for recovery does not mutate the ledger or release holds');
  financeRestart(['credits' => FALSE, 'billing' => FALSE], FALSE);
  check(snapshot() === $before, 'Rejected shutdown preserves every ledger row');
  financeRestart();
  $final = ['operation_id' => 'finance-pending', 'delivery' => 'none', 'delivery_ref' => 'synthetic-recovery',
    'attempts' => [['attempt_id' => 'finance-pending', 'revision' => 1]]];
  $unknown = financeFinalize($final);
  check($unknown['status'] === 200 && $unknown['data']['pending_reason'] === 'usage_incomplete',
    'Recovery never guesses that an unknown attempt can release its hold');
  check(snapshot() === $before, 'Unknown provider outcome preserves financial amounts');
  $pending = array_replace($attempt, ['operation_id' => 'finance-pending', 'attempt_id' => 'finance-pending',
    'state' => 'unknown', 'dispatch_state' => 'unknown', 'input_tokens_total' => NULL, 'output_tokens_total' => NULL,
    'usage_quality' => 'missing', 'last_event_id' => 2]);
  $db->insert('ai_provider_attempt')->fields($pending)->execute();
  $unknown = financeFinalize($final);
  check($unknown['status'] === 200 && $unknown['data']['pending_reason'] === 'execution_unresolved'
    && snapshot() === $before, 'An explicit unknown observation still preserves the hold');
  $db->update('ai_provider_attempt')->fields(['state' => 'not_sent', 'dispatch_state' => 'not_sent'])
    ->condition('attempt_id', 'finance-pending')->execute();
  $closed = financeFinalize($final);
  check($closed['status'] === 200 && $closed['data']['status'] === 'released'
    && $closed['data']['released_atoms'] === '1200', 'Confirmed unstarted attempt releases through the signed recovery route');
  $history = snapshot();
  check(financeFinalize($final)['data'] === $closed['data'] && snapshot() === $history,
    'Signed recovery redelivery never changes the terminal ledger outcome');
  financeRestart();
  financeState('All financial work closed', FALSE, FALSE, FALSE);
  check(snapshot() === $history && (int) $db->select('ai_billing_authorization')->countQuery()->execute()->fetchField() === 1,
    'Disabled services preserve closed authorizations and all ledger history');
  financeRestart(['credits' => TRUE, 'billing' => TRUE]);
  financeState('Explicit recovery for Node pending delivery', TRUE, FALSE, TRUE);
  financeRestart(['credits' => 'false'], FALSE);
  financeRestart();
  check(snapshot() === $history, 'Invalid configuration and recovery assembly leave historical balances unchanged');
  financeRestart([], TRUE, 'off');
  $off = financeHttp('GET', '/api/v3/ai/site-finance');
  check($off['status'] === 200 && $off['data']['metering'] === 'off',
    'Installed usage can publish an explicit disabled snapshot');
  check(!\Drupal::hasService('xinshi_ai_usage.local_producer') && !\Drupal::hasService('xinshi_ai_usage.report'),
    'Disabled usage removes producer and report services from the real compiled container');
  foreach (['/api/v3/ai/usage/me/summary', '/api/v3/ai/admin/reports/summary'] as $path) {
    check(financeHttp('GET', $path, $fixture . '/finance-admin.cookie')['status'] === 404,
      'Disabled usage removes real HTTP route: ' . $path);
  }
  check(snapshot() === $history, 'Disabling metering preserves closed financial history');
  financeRestart();
  financeState('Metering restored', FALSE, FALSE, FALSE);
  echo 'Finance runtime checks: ' . $checks . PHP_EOL;
}
finally { proc_terminate($server); proc_close($server); }
