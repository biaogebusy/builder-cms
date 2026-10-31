<?php

declare(strict_types=1);

require __DIR__ . '/../../xinshi_credits/tests/bootstrap.php';
require __DIR__ . '/../../xinshi_credits/tests/fixtures.php';
set_exception_handler(static function (Throwable $error): never { fwrite(STDERR, (string) $error . PHP_EOL); exit(1); });
file_put_contents($root . '/docroot/sites/default/settings.php', "\n\$settings['xinshi_ai_usage.mode'] = 'enforce';\n\$settings['xinshi_ai_usage.site_id'] = 'trial-fixture';\n\$settings['xinshi_ai_usage.producers'] = ['trial-node' => ['site_id' => 'trial-fixture', 'secret' => 'synthetic-only']];\n", FILE_APPEND);
\Drupal::service('theme_installer')->install(['claro']);
\Drupal::configFactory()->getEditable('system.theme')->set('admin', 'claro')->save();
\Drupal\user\Entity\Role::load('trial_admin')->grantPermission('view the administration theme')->save();
\Drupal::service('router.builder')->rebuild();
$server = proc_open([PHP_BINARY, '-d', 'extension=pdo_sqlite', '-d', 'extension=pdo_mysql', '-S', '127.0.0.1:8080',
  __DIR__ . '/../../xinshi_credits/tests/http.php'], [0 => ['file', '/dev/null', 'r'],
  1 => ['file', $fixture . '/http.log', 'a'], 2 => ['file', $fixture . '/http.log', 'a']], $pipes);
function trialHttp(string $method, string $path, ?string $jar = NULL, array $values = [], bool $json = FALSE): array {
  $curl = curl_init('http://127.0.0.1:8080' . $path); $cache = '';
  curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => TRUE, CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => ['Content-Type: ' . ($json ? 'application/json' : 'application/x-www-form-urlencoded')],
    CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$cache): int {
      if (str_starts_with(strtolower($line), 'cache-control:')) $cache = strtolower($line);
      return strlen($line);
    }]);
  if ($jar) { curl_setopt($curl, CURLOPT_COOKIEFILE, $jar); curl_setopt($curl, CURLOPT_COOKIEJAR, $jar); }
  if ($method === 'POST') curl_setopt($curl, CURLOPT_POSTFIELDS, $json ? json_encode($values) : http_build_query($values));
  $body = curl_exec($curl); $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE); curl_close($curl);
  if ($body === FALSE) throw new RuntimeException('Synthetic trial HTTP failed');
  if (str_contains($path, '/credits/trial')) check(str_contains($cache, 'private') && str_contains($cache, 'no-store'), 'Trial configuration response is private: ' . $status);
  return ['status' => $status, 'body' => $body];
}
function trialForm(string $html): array {
  $document = new DOMDocument(); @$document->loadHTML($html); $fields = [];
  foreach ((new DOMXPath($document))->query('//form[@id="xinshi-ai-credit-trial"]//input[@type="hidden"]') as $node) {
    $fields[$node->getAttribute('name')] = $node->getAttribute('value');
  }
  if (!isset($fields['form_token'], $fields['form_build_id'])) throw new RuntimeException('Missing trial form: ' . strip_tags($html));
  return $fields;
}
function trialAcknowledged(string $html): bool {
  $document = new DOMDocument(); @$document->loadHTML($html);
  return (new DOMXPath($document))->query('//input[@name="provider_cap"][@checked]')->length === 1;
}
try {
  for ($i = 0; $i < 60; $i++) {
    if ($socket = @fsockopen('127.0.0.1', 8080, $errno, $error, 0.1)) { fclose($socket); break; }
    usleep(50000);
  }
  $path = '/admin/config/xinshi/ai/credits/trial'; $jar = $fixture . '/trial-admin.cookie';
  check(trialHttp('GET', $path)['status'] === 403, 'Anonymous cannot open trial configuration');
  check(trialHttp('POST', '/user/login?_format=json', $jar, ['name' => 'trial_admin', 'pass' => 'synthetic-pass'], TRUE)['status'] === 200, 'Trial administrator logs in through Cookie authentication');
  $form = trialHttp('GET', $path, $jar);
  check($form['status'] === 200, 'Trial administrator can open the setup');
  check(!str_contains($form['body'], 'name="image_model"') && !str_contains($form['body'], 'name="image"'), 'Text trial form does not require image configuration');
  file_put_contents($fixture . '/ui/trial.html', $form['body']);
  $input = ['welcome' => '15', 'daily' => '1', 'chat' => '1', 'chat_model' => 'trial-chat', 'role' => 'trial_participant', 'timezone' => 'UTC', 'expires_at' => '',
    'provider_cap' => '1', 'review' => 'Review trial settings'];
  $configured = \Drupal::config('xinshi_ai_billing.trial')->getRawData();
  \Drupal::service('cache_tags.invalidator.checksum')->reset();
  \Drupal::configFactory()->getEditable('xinshi_ai_billing.trial')->clear('version')->save();
  try {
    $unconfigured = trialHttp('GET', $path, $jar);
    check(!trialAcknowledged($unconfigured['body']), 'A setup without a saved version requires prerequisite confirmation');
    file_put_contents($fixture . '/ui/trial-unconfigured.html', $unconfigured['body']);
    $unconfirmed = $input; unset($unconfirmed['provider_cap']);
    $before = snapshot();
    $invalid = trialHttp('POST', $path, $jar, trialForm($unconfigured['body']) + $unconfirmed);
    check($invalid['status'] === 200 && !str_contains($invalid['body'], 'name="apply"')
      && snapshot() === $before, 'Missing prerequisite confirmation cannot reach review or publish settings');
    \Drupal::configFactory()->reset();
    check(!\Drupal::config('xinshi_ai_billing.trial')->get('version'), 'Failed validation does not retain prerequisite confirmation');
  }
  finally {
    \Drupal::service('cache_tags.invalidator.checksum')->reset();
    \Drupal::configFactory()->getEditable('xinshi_ai_billing.trial')->setData($configured)->save();
  }
  $before = snapshot();
  $review = trialHttp('POST', $path, $jar, trialForm($form['body']) + $input);
  check($review['status'] === 200 && str_contains($review['body'], 'Save and enable trial credits'), 'Form API advances from valid values to review');
  check(snapshot() === $before, 'Review does not publish or activate settings');
  file_put_contents($fixture . '/ui/trial-review.html', $review['body']);
  $confirm = trialForm($review['body']) + ['apply' => 'Save and enable trial credits'];
  trialHttp('POST', $path, $jar, array_replace($confirm, ['form_token' => 'invalid']));
  check(snapshot() === $before, 'Invalid CSRF cannot publish trial settings');
  $result = trialHttp('POST', $path, $jar, $confirm + ['welcome' => '999']);
  check($result['status'] === 303, 'Cached form services survive confirmation and redirect after saving');
  \Drupal::configFactory()->reset();
  $saved = \Drupal::config('xinshi_ai_billing.trial')->getRawData();
  check($saved['welcome'] === '15' && $saved['expires_at'] === NULL, 'Confirmation ignores unreviewed overrides and retains no expiry');
  $before = snapshot();
  trialHttp('POST', $path, $jar, $confirm);
  check(snapshot() === $before, 'Repeated form confirmation cannot republish rewards');
  $form = trialHttp('GET', $path, $jar);
  check(trialAcknowledged($form['body']), 'Saved prerequisite confirmation remains checked after a fresh GET');
  check(str_contains($form['body'], 'All enabled'), 'Fresh GET shows all Drupal credit switches enabled');
  file_put_contents($fixture . '/ui/trial-enabled.html', $form['body']);
  foreach (['xinshi_credits.settings' => ['grants_enabled', 'spending_enabled'],
    'xinshi_rewards.settings' => ['welcome_enabled', 'daily_enabled'],
    'xinshi_ai_billing.settings' => ['enabled']] as $name => $keys) {
    foreach ($keys as $key) {
      \Drupal::service('cache_tags.invalidator.checksum')->reset();
      \Drupal::configFactory()->getEditable($name)->set($key, FALSE)->save();
      $disabled = trialHttp('GET', $path, $jar);
      check(str_contains($disabled['body'], 'Not all enabled.') && trialAcknowledged($disabled['body']),
        'Disabled business switch is distinct from saved confirmation: ' . $key);
      \Drupal::configFactory()->reset();
      check(\Drupal::config($name)->get($key) === FALSE, 'Refreshing does not re-enable ' . $key);
      file_put_contents($fixture . '/ui/trial-disabled.html', $disabled['body']);
      \Drupal::service('cache_tags.invalidator.checksum')->reset();
      \Drupal::configFactory()->getEditable($name)->set($key, TRUE)->save();
    }
  }
  $form = trialHttp('GET', $path, $jar);
  check(str_contains($form['body'], 'All enabled') && snapshot() === $before,
    'Refreshing shows restored switches without changing the ledger');
  $review = trialHttp('POST', $path, $jar, trialForm($form['body']) + $input);
  // Treat this role edit as a new request after the HTTP server rebuilt caches.
  \Drupal::service('cache_tags.invalidator.checksum')->reset();
  \Drupal::entityTypeManager()->getStorage('user_role')->resetCache(['trial_admin']);
  $role = \Drupal\user\Entity\Role::load('trial_admin');
  $role->revokePermission('administer permissions')->save();
  check(trialHttp('POST', $path, $jar, trialForm($review['body']) + ['apply' => 'Save and enable trial credits'])['status'] === 403,
    'Revoked permission blocks an already reviewed form');
  check(snapshot() === $before, 'Revoked administrator cannot change the ledger');
  // Simulate a new request after the HTTP denial cached the revoked role.
  \Drupal::service('cache_tags.invalidator.checksum')->reset();
  \Drupal::entityTypeManager()->getStorage('user_role')->resetCache(['trial_admin']);
  $role = \Drupal\user\Entity\Role::load('trial_admin');
  $role->grantPermission('administer permissions')->save();
  check(trialHttp('GET', $path, $jar)['status'] === 200, 'Restored permission allows trial configuration again');
  foreach (glob($fixture . '/ui/trial*.html') as $page) {
    preg_match_all('/(?:href|src)="([^" ]+\.(?:css|js)[^" ]*)"/', file_get_contents($page), $matches);
    foreach ($matches[1] as $url) {
      $url = html_entity_decode($url); $assetPath = parse_url($url, PHP_URL_PATH);
      if (str_starts_with($assetPath, '/core/')) continue;
      if (!preg_match('@^/sites/default/files/(css|js)/@', $assetPath)) throw new RuntimeException('Unexpected synthetic asset');
      $asset = trialHttp('GET', $url, $jar);
      check($asset['status'] === 200, 'Trial stylesheet is available');
      $target = $fixture . '/ui' . $assetPath;
      if (!is_dir(dirname($target))) mkdir(dirname($target), 0777, TRUE);
      file_put_contents($target, $asset['body']);
    }
  }
}
finally { proc_terminate($server); proc_close($server); }
echo 'Trial HTTP checks: ' . $checks . PHP_EOL;
