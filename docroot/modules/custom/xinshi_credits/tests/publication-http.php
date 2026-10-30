<?php

declare(strict_types=1);

// Reuse the running loopback server and authenticated HTTP helpers from http.php.
$publicationChecks = $checks;
\Drupal\user\Entity\Role::create(['id' => 'http_publisher', 'label' => 'HTTP publisher', 'permissions' => ['administer credit budgets']])->save();
$publisherUser = \Drupal\user\Entity\User::create(['name' => 'http-publisher', 'status' => 1, 'pass' => 'credits-test-password', 'roles' => ['http_publisher']]);
$publisherUser->save();
$creditsConfig = \Drupal::configFactory()->getEditable('xinshi_credits.settings');
$creditsConfig->set('site_id', 'http-publication')->set('grants_enabled', FALSE)->set('spending_enabled', FALSE)->save();
$jar = $fixture . '/publisher.cookie';
status(http('POST', '/user/login?_format=json', $jar, json_encode(['name' => 'http-publisher', 'pass' => 'credits-test-password']), ['Content-Type: application/json']), 200, 'Publisher Cookie login');
$path = '/admin/config/people/credits/budgets';
foreach ([NULL, $jars['alice']] as $identity) status(http('GET', $path, $identity), 403, 'Budget UI rejects users without publication permission');
status(http('GET', $path, headers: ['Authorization: Bearer ' . $bearer]), 403, 'Administrative form does not accept OAuth authentication');

function publicationForm(string $html): array {
  $document = new DOMDocument();
  @$document->loadHTML($html);
  $xpath = new DOMXPath($document); $fields = [];
  foreach ($xpath->query('//form[@id="xinshi-credit-publication"]//input[@name]') as $node) {
    if (in_array($node->getAttribute('type'), ['hidden', 'text'], TRUE)) $fields[$node->getAttribute('name')] = $node->getAttribute('value');
  }
  if (!isset($fields['form_build_id'], $fields['form_token'])) throw new RuntimeException('Expected protected publication form: ' . strip_tags($html));
  return $fields;
}
function publicationPost(string $path, string $jar, array $fields): array {
  return http('POST', $path, $jar, http_build_query($fields), ['Content-Type: application/x-www-form-urlencoded']);
}
function publicationCount(): int {
  return (int) \Drupal::database()->select('credit_budget_bucket', 'b')->condition('site_id', 'http-publication')->countQuery()->execute()->fetchField();
}

mkdir($fixture . '/ui');
$response = http('GET', $path, $jar);
status($response, 200, 'Publisher sees cost form');
file_put_contents($fixture . '/ui/budget.html', $response['body']);
$initial = publicationForm($response['body']);
check($initial['cap'] === '' && $initial['headroom'] === '' && $initial['currency'] === '', 'Budget UI supplies no guessed economics');
$values = array_replace($initial, ['publication_ref' => 'http-budget', 'source_ref' => '<img src=x onerror=alert(1)>',
  'period_start' => gmdate('Y-m-d H:i', time() - 60), 'period_end' => gmdate('Y-m-d H:i', time() + 86400),
  'category' => 'chat', 'currency' => 'CNY', 'cap' => '0.01', 'headroom' => '0.001', 'review' => 'Review publication']);
$forged = $values; unset($forged['form_token']);
$invalid = publicationPost($path, $jar, $forged);
check(publicationCount() === 0 && !str_contains($invalid['body'], 'name="publish"'), 'Missing CSRF cannot advance to publication');
$response = publicationPost($path, $jar, $values);
status($response, 200, 'Valid input opens review');
check(publicationCount() === 0 && str_contains($response['body'], 'name="publish"'), 'Review shows confirmation without publishing');
check(!str_contains($response['body'], '<img src=x'), 'Review escapes untrusted evidence text');
file_put_contents($fixture . '/ui/review.html', $response['body']);
$review = publicationForm($response['body']);
$back = publicationPost($path, $jar, $review + ['back' => 'Back to edit']);
status($back, 200, 'Back restores the editable form');
$restored = publicationForm($back['body']);
check($restored['cap'] === '0.01' && $restored['publication_ref'] === 'http-budget', 'Rebuilt form preserves draft and reference');
$response = publicationPost($path, $jar, array_replace($values, $restored));
$review = publicationForm($response['body']);
$confirm = $review + ['publish' => 'Publish immutable version'];
$invalid = $confirm; $invalid['form_token'] = 'invalid';
publicationPost($path, $jar, $invalid);
check(publicationCount() === 0, 'Invalid confirmation CSRF does not publish');
$publicationDb = \Drupal::database();
$trigger = $publicationDb->driver() === 'mysql'
  ? "CREATE TRIGGER reject_http_budget BEFORE INSERT ON {credit_budget_bucket} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'private synthetic failure'"
  : "CREATE TRIGGER reject_http_budget BEFORE INSERT ON {credit_budget_bucket} BEGIN SELECT RAISE(ABORT, 'private synthetic failure'); END";
$publicationDb->query($trigger, [], ['allow_delimiter_in_query' => TRUE]);
$failure = publicationPost($path, $jar, $confirm);
$publicationDb->query('DROP TRIGGER reject_http_budget');
check(publicationCount() === 0 && str_contains($failure['body'], 'Retry with the same reference.')
  && !str_contains($failure['body'], 'private synthetic failure'), 'Database failure retains review and hides internal errors');
$confirm = publicationForm($failure['body']) + ['publish' => 'Publish immutable version'];
status(publicationPost($path, $jar, $confirm + ['cap' => '999999']), 303, 'Valid confirmation publishes then redirects');
check(publicationCount() === 1, 'One reviewed budget is published');
check((int) $publicationDb->select('credit_budget_bucket', 'b')->fields('b', ['cap_micros'])->condition('site_id', 'http-publication')->execute()->fetchField() === 10000,
  'Confirmation uses the reviewed values, not new posted amounts');
publicationPost($path, $jar, $confirm);
check(publicationCount() === 1, 'Repeated confirmation does not duplicate the budget');
$response = http('GET', $path, $jar);
check(!str_contains($response['body'], '<img src=x') && str_contains($response['body'], 'http-budget'), 'Catalog escapes and retains immutable evidence');
file_put_contents($fixture . '/ui/catalog.html', $response['body']);
$bucket = \Drupal::database()->select('credit_budget_bucket', 'b')->fields('b', ['id'])->condition('site_id', 'http-publication')->execute()->fetchField();
$path = '/admin/config/people/credits/pools';
$response = http('GET', $path, $jar);
status($response, 200, 'Publisher sees allocation form');
file_put_contents($fixture . '/ui/pool.html', $response['body']);
$fields = publicationForm($response['body']);
$response = publicationPost($path, $jar, $fields + ['add_bound' => 'Add category']);
file_put_contents($fixture . '/ui/pool-add.html', $response['body']);
check(str_contains($response['body'], 'bounds[2][bucket_id]'), 'Adding a category rebuilds a cached form with restored services');
$fields = publicationForm($response['body']);
$poolValues = array_replace($fields, ['publication_ref' => 'http-pool', 'source_ref' => 'Synthetic rule evidence',
  'period_start' => gmdate('Y-m-d H:i', time()), 'period_end' => gmdate('Y-m-d H:i', time() + 7200),
  'expires_at' => gmdate('Y-m-d H:i', time() + 3600), 'rule_id' => 'daily-fixture', 'rule_version' => 'v1',
  'mode' => 'fixed', 'source_kind' => 'promotion', 'cap' => '2', 'grant_limit' => '1',
  'bounds[0][bucket_id]' => $bucket, 'bounds[0][rate]' => '0.001', 'bounds[0][source_ref]' => 'Synthetic cost bound',
  'bounds[1][bucket_id]' => '', 'bounds[2][bucket_id]' => '', 'review' => 'Review publication']);
$response = publicationPost($path, $jar, $poolValues);
check(str_contains($response['body'], 'name="publish"'), 'Allocation enters review with selected budget');
file_put_contents($fixture . '/ui/pool-review.html', $response['body']);
status(publicationPost($path, $jar, publicationForm($response['body']) + ['publish' => 'Publish immutable version']), 303, 'Allocation publishes through Form API');
check((int) \Drupal::database()->select('credit_reward_pool', 'p')->condition('site_id', 'http-publication')->countQuery()->execute()->fetchField() === 1, 'Allocation form saves one immutable rule');
check((int) \Drupal::database()->select('credit_journal', 'j')->condition('site_id', 'http-publication')->countQuery()->execute()->fetchField() === 0, 'Administrative publication creates no user ledger entries');
check($creditsConfig->get('grants_enabled') === FALSE && $creditsConfig->get('spending_enabled') === FALSE, 'Administrative publication leaves switches paused');
foreach (glob($fixture . '/ui/*.html') as $page) {
  preg_match_all('/(?:href|src)="([^" ]+\.(?:css|js)[^" ]*)"/', file_get_contents($page), $matches);
  foreach ($matches[1] as $url) {
    $url = html_entity_decode($url); $assetPath = parse_url($url, PHP_URL_PATH);
    if (str_starts_with($assetPath, '/core/')) continue;
    if (!preg_match('@^/sites/default/files/(css|js)/@', $assetPath)) throw new RuntimeException('Unexpected synthetic asset');
    $asset = http('GET', $url, $jar);
    if ($asset['status'] !== 200) throw new RuntimeException('Synthetic asset unavailable');
    $target = $fixture . '/ui' . $assetPath;
    if (!is_dir(dirname($target))) mkdir(dirname($target), 0777, TRUE);
    file_put_contents($target, $asset['body']);
  }
}
$publisherUser->block()->save();
status(http('GET', $path, $jar), 403, 'Blocked publisher cannot keep using the form');
$creditsConfig->set('site_id', 'fixture')->set('grants_enabled', TRUE)->save();
echo 'Publication HTTP checks: ' . ($checks - $publicationChecks) . PHP_EOL;
