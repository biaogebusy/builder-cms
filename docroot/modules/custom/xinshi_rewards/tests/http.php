<?php

declare(strict_types=1);

use Drupal\simple_oauth\Entity\Oauth2Scope;
use Drupal\user\Entity\User;
use League\OAuth2\Server\CryptKey;

require __DIR__ . '/../../xinshi_credits/tests/bootstrap.php';
\Drupal::configFactory()->getEditable('xinshi_rewards.settings')->set('daily_enabled', TRUE)->set('admin_gifts_enabled', TRUE)->save();
$db->update('credit_reward_pool')->fields(['cap_atoms' => 100000])->condition('site_id', 'reward-fixture')->condition('rule_id', 'G11')->execute();
$user = User::create(['name' => 'rewards-http', 'status' => 1, 'pass' => 'synthetic-pass', 'roles' => ['reward_eligible', 'credit_none']]);
$user->save();
Oauth2Scope::create(['name' => 'reward_eligible', 'granularity_id' => 'role',
  'granularity_configuration' => ['role' => 'reward_eligible'],
  'grant_types' => ['authorization_code' => ['status' => TRUE]]])->save();
function rewardToken(string $uid, string $scope): string {
  $repo = \Drupal::service('simple_oauth.repositories.access_token');
  $client = \Drupal::service('simple_oauth.repositories.client')->getClientEntity('credits-http');
  $scope = \Drupal::service('simple_oauth.repositories.scope')->getScopeEntityByIdentifier($scope);
  $token = $repo->getNewToken($client, [$scope], $uid);
  $token->setIdentifier(bin2hex(random_bytes(24)));
  $token->setExpiryDateTime(new DateTimeImmutable('+1 hour'));
  $token->setPrivateKey(new CryptKey('/tmp/credits-test/private.pem'));
  $repo->persistNewAccessToken($token);
  return $token->convertToJWT()->toString();
}
function rewardHttp(string $method, string $path, ?string $jar = NULL, string $body = '', array $headers = []): array {
  $cache = ''; $curl = curl_init('http://127.0.0.1:8080' . $path);
  curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_HTTPHEADER => ['Accept: application/json', ...$headers], CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 30,
    CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$cache): int {
      if (str_starts_with(strtolower($line), 'cache-control:')) $cache = strtolower($line);
      return strlen($line);
    }]);
  if ($method !== 'GET') curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
  if ($jar) { curl_setopt($curl, CURLOPT_COOKIEFILE, $jar); curl_setopt($curl, CURLOPT_COOKIEJAR, $jar); }
  $content = curl_exec($curl); $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE); curl_close($curl);
  if ($content === FALSE) throw new RuntimeException('Synthetic loopback HTTP failed');
  if (str_contains($path, '/credits/')) check(str_contains($cache, 'private') && str_contains($cache, 'no-store'), 'Reward response is private: ' . $status);
  return ['status' => $status, 'body' => $content, 'data' => json_decode($content, TRUE)];
}
function rewardForm(string $html): array {
  $document = new DOMDocument(); @$document->loadHTML($html); $fields = [];
  foreach ((new DOMXPath($document))->query('//form[@id="xinshi-rewards-gift"]//input[@name]') as $node) {
    if (in_array($node->getAttribute('type'), ['hidden', 'text'], TRUE)) $fields[$node->getAttribute('name')] = $node->getAttribute('value');
  }
  if (!isset($fields['form_token'], $fields['form_build_id'])) throw new RuntimeException('Missing protected gift form: ' . strip_tags($html));
  return $fields;
}
\Drupal::service('router.builder')->rebuild();
check(\Drupal::service('router.route_provider')->getRouteByName('xinshi_rewards.status')->getPath() === '/api/v3/credits/me/rewards',
  'Optional rewards routes are rebuilt');
$server = proc_open([PHP_BINARY, '-d', 'extension=pdo_sqlite', '-d', 'extension=pdo_mysql', '-S', '127.0.0.1:8080',
  __DIR__ . '/../../xinshi_credits/tests/http.php'], [0 => ['file', '/dev/null', 'r'],
    1 => ['file', $fixture . '/http.log', 'a'], 2 => ['file', $fixture . '/http.log', 'a']], $pipes);
try {
  for ($i = 0; $i < 60; $i++) {
    if ($socket = @fsockopen('127.0.0.1', 8080, $errno, $error, 0.1)) { fclose($socket); break; }
    usleep(50000);
  }
  $jars = [];
  foreach (['rewards-http', 'reward_admin'] as $name) {
    $jars[$name] = $fixture . '/' . $name . '.cookie';
    check(rewardHttp('POST', '/user/login?_format=json', $jars[$name], json_encode(['name' => $name, 'pass' => 'synthetic-pass']),
      ['Content-Type: application/json'])['status'] === 200, 'Reward Cookie login: ' . $name);
  }
  $jar = $jars['rewards-http'];
  $path = '/api/v3/credits/me/daily-visit';
  $csrf = rewardHttp('GET', '/session/token', $jar)['body'];
  $headers = ['Content-Type: application/json', 'Idempotency-Key: http-visit'];
  $protected = [...$headers, 'X-CSRF-Token: ' . $csrf, 'Origin: http://127.0.0.1:8080'];
  check(rewardHttp('GET', '/api/v3/credits/me/rewards')['status'] === 403, 'Anonymous rewards denied');
  check(rewardHttp('POST', $path, $jar, '{}', $headers)['status'] === 403, 'Cookie writes require CSRF');
  check(rewardHttp('POST', $path, $jar, '{}', [...$headers, 'X-CSRF-Token: ' . $csrf])['status'] === 403, 'Cookie writes require Origin');
  check(rewardHttp('POST', $path, $jar, '{}', [...$headers, 'X-CSRF-Token: ' . $csrf, 'Origin: https://untrusted.example'])['status'] === 403, 'Foreign Origin rejected');
  check(rewardHttp('POST', $path, $jar, '[]', $protected)['status'] === 422, 'Daily request must be an object');
  check(rewardHttp('POST', $path, $jar, '{"beneficiary_user_id":"1"}', $protected)['status'] === 422, 'Daily caller cannot choose the beneficiary');
  $first = rewardHttp('POST', $path, $jar, '{}', $protected);
  check($first['status'] === 200 && $first['data']['status'] === 'granted', 'Protected Cookie activity grants credits');
  check(rewardHttp('POST', $path, $jar, '{}', $protected)['data'] === $first['data'], 'HTTP retry preserves original receipt');
  $bearer = rewardToken((string) $user->id(), 'reward_eligible');
  $oauth = rewardHttp('POST', $path, NULL, '{}', [...$headers, 'Authorization: Bearer ' . $bearer]);
  check($oauth['status'] === 200 && $oauth['data'] === $first['data'], 'Pure OAuth write needs no Cookie CSRF or Origin');
  foreach ([rewardToken((string) $user->id(), 'credit_none'), 'invalid-jwt'] as $token) {
    check(in_array(rewardHttp('POST', $path, $jar, '{}', [...$protected, 'Authorization: Bearer ' . $token])['status'], [401, 403], TRUE),
      'Invalid or restricted OAuth cannot borrow Cookie authority');
  }
  $events = rewardHttp('GET', '/api/v3/credits/me/notifications', $jar)['data']['rows'];
  check(count($events) === 1, 'One HTTP grant creates one in-app event');
  $ack = '/api/v3/credits/me/notifications/' . $events[0]['event_id'] . '/ack';
  check(rewardHttp('POST', $ack, $jar, '{}', $headers)['status'] === 403, 'Notification ACK requires Cookie CSRF');
  check(rewardHttp('POST', $ack, $jar, '{}', $protected)['status'] === 200, 'Owned notification ACK succeeds');
  check(rewardHttp('POST', $ack, $jar, '{}', $protected)['status'] === 200, 'Notification ACK can be repeated');
  $adminPath = '/api/v3/credits/admin/users/' . $user->id() . '/account';
  check(rewardHttp('GET', $adminPath, $jar)['status'] === 403, 'Ordinary user cannot invoke administrator reads');
  check(rewardHttp('GET', $adminPath, $jars['reward_admin'])['status'] === 200, 'Administrator reads target under current entity access');
  $adminCsrf = rewardHttp('GET', '/session/token', $jars['reward_admin'])['body'];
  $giftHeaders = [...$headers, 'Origin: http://127.0.0.1:8080', 'X-CSRF-Token: ' . $adminCsrf];
  $pool = $db->select('credit_reward_pool', 'p')->fields('p', ['id'])->condition('site_id', 'reward-fixture')
    ->condition('rule_id', 'G12')->execute()->fetchField();
  $gift = ['beneficiary_user_id' => (string) user_load_by_name('reward_ineligible')->id(), 'amount_atoms' => '125',
    'budget_pool_id' => $pool, 'scope' => ['chat', 'image'], 'expires_at' => time() + 3600,
    'public_reason' => 'Synthetic API gift', 'internal_note' => 'PRIVATE API NOTE'];
  check(rewardHttp('POST', '/api/v3/credits/admin/grants', $jar, json_encode($gift), $protected)['status'] === 403,
    'Ordinary user cannot call administrator gift API');
  $apiGift = rewardHttp('POST', '/api/v3/credits/admin/grants', $jars['reward_admin'], json_encode($gift), $giftHeaders);
  check($apiGift['status'] === 200 && $apiGift['data']['receipt']['amount_atoms'] === '125', 'Authorized gift API commits the explicit amount');
  check(rewardHttp('POST', '/api/v3/credits/admin/grants', $jars['reward_admin'], json_encode($gift), $giftHeaders)['data'] === $apiGift['data'],
    'Administrator HTTP retry preserves one gift');
  $gift['amount_atoms'] = '126';
  check(rewardHttp('POST', '/api/v3/credits/admin/grants', $jars['reward_admin'], json_encode($gift), $giftHeaders)['status'] === 409,
    'Administrator HTTP key cannot authorize changed amounts');
  $giftPath = '/admin/config/people/credits/users/' . $user->id() . '/gift';
  check(rewardHttp('GET', $giftPath, $jar)['status'] === 403, 'Gift form requires management permissions');
  $response = rewardHttp('GET', $giftPath, $jars['reward_admin']);
  check($response['status'] === 200, 'Administrator sees gift form');
  file_put_contents($fixture . '/ui/gift.html', $response['body']);
  $fields = rewardForm($response['body']);
  check($fields['amount'] === '' && $fields['expiry'] === '', 'Gift form invents no amount or expiry');
  $before = (int) $db->select('credit_journal', 'j')->condition('site_id', 'reward-fixture')->countQuery()->execute()->fetchField();
  $values = array_replace($fields, ['amount' => '0.125', 'public_reason' => '<img src=x onerror=alert(1)>',
    'internal_note' => 'PRIVATE HTTP NOTE', 'expiry' => gmdate('Y-m-d H:i:s', time() + 3600), 'op' => 'Review gift']);
  $formHeaders = ['Content-Type: application/x-www-form-urlencoded'];
  $response = rewardHttp('POST', $giftPath, $jars['reward_admin'], http_build_query($values), $formHeaders);
  check($response['status'] === 200 && str_contains($response['body'], 'Confirm gift'), 'Form API advances to review');
  check(!str_contains($response['body'], '<img src=x'), 'Gift review escapes user-entered reasons');
  check((int) $db->select('credit_journal', 'j')->condition('site_id', 'reward-fixture')->countQuery()->execute()->fetchField() === $before, 'Review has no financial effects');
  file_put_contents($fixture . '/ui/gift-review.html', $response['body']);
  $confirm = rewardForm($response['body']) + ['op' => 'Confirm gift'];
  $invalid = $confirm; $invalid['form_token'] = 'invalid';
  rewardHttp('POST', $giftPath, $jars['reward_admin'], http_build_query($invalid), $formHeaders);
  check((int) $db->select('credit_journal', 'j')->condition('site_id', 'reward-fixture')->countQuery()->execute()->fetchField() === $before, 'Invalid form token cannot give credits');
  $response = rewardHttp('POST', $giftPath, $jars['reward_admin'], http_build_query($confirm + ['amount' => '999']), $formHeaders);
  check($response['status'] === 200 && str_contains($response['body'], 'Gift committed'), 'Cached form services survive confirmation');
  file_put_contents($fixture . '/ui/gift-result.html', $response['body']);
  check((int) $db->select('credit_journal', 'j')->condition('site_id', 'reward-fixture')->countQuery()->execute()->fetchField() === $before + 1, 'Confirmation commits exactly one reviewed gift');
  rewardHttp('POST', $giftPath, $jars['reward_admin'], http_build_query($confirm), $formHeaders);
  check((int) $db->select('credit_journal', 'j')->condition('site_id', 'reward-fixture')->countQuery()->execute()->fetchField() === $before + 1, 'Repeated confirmation cannot duplicate the gift');
  check(rewardHttp('GET', '/api/v3/credits/me/account', $jar)['data']['available_atoms'] === '1125', 'Confirmation ignores unreviewed amount overrides');
  foreach (glob($fixture . '/ui/gift*.html') as $page) {
    preg_match_all('/(?:href|src)="([^" ]+\.(?:css|js)[^" ]*)"/', file_get_contents($page), $matches);
    foreach ($matches[1] as $url) {
      $url = html_entity_decode($url); $assetPath = parse_url($url, PHP_URL_PATH);
      if (str_starts_with($assetPath, '/core/')) continue;
      if (!preg_match('@^/sites/default/files/(css|js)/@', $assetPath)) throw new RuntimeException('Unexpected synthetic asset');
      $asset = rewardHttp('GET', $url, $jars['reward_admin']);
      if ($asset['status'] !== 200) throw new RuntimeException('Synthetic gift asset unavailable');
      $target = $fixture . '/ui' . $assetPath;
      if (!is_dir(dirname($target))) mkdir(dirname($target), 0777, TRUE);
      file_put_contents($target, $asset['body']);
    }
  }
}
finally { proc_terminate($server); proc_close($server); }
echo 'Reward HTTP checks: ' . $checks . PHP_EOL;
