<?php

declare(strict_types=1);

use Drupal\simple_oauth\Entity\Oauth2Scope;
use Drupal\user\Entity\User;
use Drupal\xinshi_ai_usage\Service\ProducerIdentity;
use League\OAuth2\Server\CryptKey;

require __DIR__ . '/../../xinshi_credits/tests/bootstrap.php';

$users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['name' => 'ai_credit_user']);
$billingUser = reset($users);
$billingUser->setPassword('synthetic-pass')->save();
$uid = (string) $billingUser->id();
Oauth2Scope::create(['name' => 'ai_credit_user', 'granularity_id' => 'role',
  'granularity_configuration' => ['role' => 'ai_credit_user'],
  'grant_types' => ['authorization_code' => ['status' => TRUE]]])->save();
file_put_contents($root . '/docroot/sites/default/settings.php',
  "\n\$settings['xinshi_ai_usage.producers'] = ['fixture-node' => ['secret' => 'synthetic-billing-secret', 'site_id' => 'fixture']];\n", FILE_APPEND);
\Drupal::service('router.builder')->rebuild();
check(\Drupal::service('router.route_provider')->getRouteByName('xinshi_ai_billing.quote')->getPath()
  === '/api/v3/credits/ai/quotes', 'Installed billing quote route is discoverable');

function billingToken(string $uid, string $scope): string {
  $repository = \Drupal::service('simple_oauth.repositories.access_token');
  $client = \Drupal::service('simple_oauth.repositories.client')->getClientEntity('credits-http');
  $scope = \Drupal::service('simple_oauth.repositories.scope')->getScopeEntityByIdentifier($scope);
  $token = $repository->getNewToken($client, [$scope], $uid);
  $token->setIdentifier(bin2hex(random_bytes(24)));
  $token->setExpiryDateTime(new DateTimeImmutable('+1 hour'));
  $token->setPrivateKey(new CryptKey('/tmp/credits-test/private.pem'));
  $repository->persistNewAccessToken($token);
  return $token->convertToJWT()->toString();
}

function billingHttp(string $method, string $path, string $body = '', array $headers = [], ?string $jar = NULL): array {
  $cache = '';
  $curl = curl_init('http://127.0.0.1:8080' . $path);
  curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_HTTPHEADER => ['Accept: application/json', ...$headers], CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 30,
    CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$cache): int {
      if (str_starts_with(strtolower($line), 'cache-control:')) $cache = strtolower($line);
      return strlen($line);
    }]);
  if ($method !== 'GET') curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
  if ($jar) { curl_setopt($curl, CURLOPT_COOKIEFILE, $jar); curl_setopt($curl, CURLOPT_COOKIEJAR, $jar); }
  $content = curl_exec($curl); $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE); curl_close($curl);
  if ($content === FALSE) throw new RuntimeException('Synthetic HTTP failed');
  if (str_contains($path, '/credits/')) check(str_contains($cache, 'private') && str_contains($cache, 'no-store'), 'Billing HTTP is private: ' . $status);
  return ['status' => $status, 'body' => $content, 'data' => json_decode($content, TRUE)];
}

function signedBilling(string $path, string $body): array {
  $timestamp = (string) time(); $nonce = bin2hex(random_bytes(16));
  return ['Content-Type: application/json', 'X-Xinshi-Producer: fixture-node',
    'X-Xinshi-Timestamp: ' . $timestamp, 'X-Xinshi-Nonce: ' . $nonce,
    'X-Xinshi-Signature: ' . ProducerIdentity::sign('synthetic-billing-secret', 'fixture-node',
      $timestamp, $nonce, 'POST', $path, $body)];
}

$server = proc_open([PHP_BINARY, '-d', 'extension=pdo_sqlite', '-d', 'extension=pdo_mysql', '-S', '127.0.0.1:8080',
  __DIR__ . '/../../xinshi_credits/tests/http.php'], [0 => ['file', '/dev/null', 'r'],
    1 => ['file', $fixture . '/http.log', 'a'], 2 => ['file', $fixture . '/http.log', 'a']], $pipes);
try {
  for ($i = 0; $i < 60; $i++) {
    if ($socket = @fsockopen('127.0.0.1', 8080, $errno, $error, 0.1)) { fclose($socket); break; }
    usleep(50000);
  }
  $path = '/api/v3/credits/ai/quotes';
  $input = json_encode(['feature' => 'text', 'model' => 'fixture-model', 'platform' => 'fixture',
    'request_hash' => str_repeat('a', 64), 'producer_id' => 'fixture-node']);
  $baseHeaders = ['Content-Type: application/json', 'Idempotency-Key: http-quote'];
  $scoped = [...$baseHeaders, 'Authorization: Bearer ' . billingToken($uid, 'ai_credit_user')];
  $anonymous = billingHttp('POST', $path, $input, $baseHeaders);
  check($anonymous['status'] === 403, 'Anonymous cannot create a quote: ' . json_encode($anonymous));
  $jar = $fixture . '/billing.cookie';
  check(billingHttp('POST', '/user/login?_format=json', json_encode(['name' => 'ai_credit_user', 'pass' => 'synthetic-pass']),
    ['Content-Type: application/json'], $jar)['status'] === 200, 'Synthetic billing Cookie login');
  check(billingHttp('POST', $path, $input, $baseHeaders, $jar)['status'] === 403, 'Cookie quotation requires CSRF');
  $csrf = billingHttp('GET', '/session/token', jar: $jar)['body'];
  check(billingHttp('POST', $path, $input, [...$baseHeaders, 'X-CSRF-Token: ' . $csrf], $jar)['status'] === 403,
    'Cookie quotation requires an allowed Origin');
  $quote = billingHttp('POST', $path, $input, $scoped);
  check($quote['status'] === 200 && isset($quote['data']['quote_id']), 'Scoped user OAuth can quote without Cookie CSRF');
  check(billingHttp('POST', $path, $input, [...$baseHeaders,
    'Authorization: Bearer ' . billingToken($uid, 'credit_none')], $jar)['status'] === 403,
    'Narrow OAuth cannot borrow broader Cookie credit permission');
  $body = json_encode(['quote_id' => $quote['data']['quote_id'], 'operation_id' => 'billing-http',
    'request_hash' => str_repeat('a', 64)]);
  $authorization = billingHttp('POST', '/api/v3/credits/ai/authorizations', $body, $scoped);
  check($authorization['status'] === 200 && $authorization['data']['state'] === 'open', 'User OAuth commits one authorization');
  check(!str_contains($authorization['body'], 'cost_limit') && !str_contains($authorization['body'], 'evidence_ref'),
    'User authorization response excludes procurement and source evidence');
  $executorPath = '/api/v3/credits/ai/executor/finalize';
  $terminal = json_encode(['operation_id' => 'billing-http', 'delivery' => 'none',
    'delivery_ref' => 'synthetic-http-not-started', 'attempts' => []]);
  check(billingHttp('POST', $executorPath, $terminal, $scoped)['status'] === 403, 'User OAuth cannot submit executor evidence');
  $signed = signedBilling($executorPath, $terminal);
  check(billingHttp('POST', $executorPath, $terminal . ' ', $signed)['status'] === 403, 'Executor signature binds exact bytes');
  $final = billingHttp('POST', $executorPath, $terminal, $signed);
  check($final['status'] === 200 && $final['data']['status'] === 'released', 'Signed producer releases confirmed unstarted execution');
  check(billingHttp('POST', $executorPath, $terminal, $signed)['status'] === 403, 'Replayed executor nonce is rejected');
  check(billingHttp('POST', $executorPath, $terminal, signedBilling($executorPath, $terminal))['data'] === $final['data'],
    'A new signature retries the same immutable ledger outcome');
  $forged = json_decode($terminal, TRUE); $forged['charged_atoms'] = '999'; $forged = json_encode($forged);
  check(billingHttp('POST', $executorPath, $forged, signedBilling($executorPath, $forged))['status'] === 422,
    'Even a signed producer cannot supply an arbitrary charge amount');
  check(billingHttp('GET', '/api/v3/credits/ai/operations/billing-http', headers: $scoped)['data'] === $final['data'],
    'Owner reads the same immutable financial outcome');
  check(billingHttp('POST', '/api/v3/credits/ai/profiles', '{}', $scoped)['status'] === 403,
    'Spending permission does not authorize sales price publication');
}
finally { proc_terminate($server); proc_close($server); }
echo 'AI billing HTTP checks: ' . $checks . PHP_EOL;
