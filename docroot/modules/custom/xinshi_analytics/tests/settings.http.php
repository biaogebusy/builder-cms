<?php

declare(strict_types=1);

/**
 * @file
 * Real Form API, permissions and configuration checks inside the HTTP fixture.
 */

use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

if (getenv('XINSHI_ANALYTICS_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv')
  || !isset($server, $fixture) || $fixture !== '/tmp/analytics-test') {
  throw new RuntimeException('Use the disposable analytics HTTP runner only.');
}

/** Requests the actual HTML form, including its session-bound CSRF processing. */
function settingsRequest(string $method, ?string $jar = NULL, array $values = [],
  string $path = '/admin/config/services/xinshi-analytics'): array {
  $curl = curl_init('http://127.0.0.1:8080' . $path);
  curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_FOLLOWLOCATION => FALSE, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => ['Accept: text/html', 'Content-Type: application/x-www-form-urlencoded']]);
  if ($jar !== NULL) {
    curl_setopt($curl, CURLOPT_COOKIEFILE, $jar);
    curl_setopt($curl, CURLOPT_COOKIEJAR, $jar);
  }
  if ($method === 'POST') {
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($values));
  }
  $body = curl_exec($curl);
  $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
  curl_close($curl);
  check(is_string($body), 'Settings form HTTP transport completes');
  return ['status' => $status, 'body' => $body];
}

/** Reads persisted configuration independently of the HTTP process. */
function savedSettings(): array {
  \Drupal::configFactory()->reset('xinshi_analytics.node_counts');
  return \Drupal::config('xinshi_analytics.node_counts')->getRawData();
}

/** Extracts actual form fields rather than fabricating CSRF or build identifiers. */
function settingsForm(string $jar): array {
  $response = settingsRequest('GET', $jar);
  check($response['status'] === 200, 'Configuration administrator can open settings');
  $document = new DOMDocument();
  @$document->loadHTML($response['body']);
  $xpath = new DOMXPath($document);
  $values = [];
  foreach (['form_id', 'form_build_id', 'form_token'] as $key) {
    $input = $xpath->query('//input[@name="' . $key . '"]')->item(0);
    check($input instanceof DOMElement && $input->getAttribute('value') !== '', 'Form supplies ' . $key);
    $values[$key] = $input->getAttribute('value');
  }
  return [$values, $xpath];
}

$originalSettings = savedSettings();
\Drupal::configFactory()->getEditable('xinshi_analytics.node_counts')->delete();
\Drupal::configFactory()->getEditable('system.date')->set('timezone.default', 'Asia/Shanghai')->save();
Role::create(['id' => 'analytics_settings_admin', 'label' => 'Analytics settings administrator',
  'permissions' => ['administer analytics datasets', 'access administration pages']])->save();
$settingsAdmin = User::create(['name' => 'analytics-settings-admin', 'pass' => $password,
  'status' => 1, 'roles' => ['analytics_settings_admin']]);
$settingsAdmin->save();
$settingsJar = $fixture . '/settings-cookies';
status(http('POST', '/user/login?_format=json', jar: $settingsJar,
  body: json_encode(['name' => $settingsAdmin->getAccountName(), 'pass' => $password])), 200, 'Settings administrator logs in');
check(settingsRequest('GET')['status'] === 403, 'Anonymous cannot read settings');
check(settingsRequest('POST', values: ['enabled' => 1])['status'] === 403, 'Anonymous cannot save settings');
check(settingsRequest('GET', $jar)['status'] === 403, 'Query permissions do not grant settings access');
check(settingsRequest('POST', $jar, ['enabled' => 1])['status'] === 403, 'Query permissions do not grant configuration writes');
check(status(http('GET', '/api/v3/analytics/capabilities', jar: $settingsJar), 200,
  'Configuration administrator capability')['permissions'] === [], 'Administration does not grant analytics.query');

[$hidden, $xpath] = settingsForm($settingsJar);
check($xpath->query('//input[@name="enabled" and @checked]')->length === 0, 'Initial enablement is unchecked');
check($xpath->query('//option[@value="Asia/Shanghai" and @selected]')->length === 1, 'Initial timezone comes from the site');
foreach (['max_calendar_months' => 12, 'max_scanned_entities' => 5000, 'max_groups' => 100, 'max_seconds' => 10] as $key => $value) {
  check($xpath->query('//input[@name="query_policy[' . $key . ']" and @value="' . $value . '"]')->length === 1,
    'Initial suggestion: ' . $key);
}
check(savedSettings() === [], 'Opening an unconfigured form does not create or enable settings');
check(str_contains(settingsRequest('GET', $settingsJar, path: '/admin/config/services')['body'],
  '/admin/config/services/xinshi-analytics'), 'Configuration menu links to settings');

$policy = ['timezone' => 'Asia/Shanghai', 'languages' => ['en' => 'en', 'fr' => 'fr'],
  'max_calendar_months' => '12', 'max_scanned_entities' => '5000', 'max_groups' => '100', 'max_seconds' => '10'];
$values = $hidden + ['enabled' => '1', 'query_policy' => $policy, 'op' => 'Save configuration'];
$badToken = $values;
$badToken['form_token'] = 'wrong-token';
settingsRequest('POST', $settingsJar, $badToken);
check(savedSettings() === [], 'An invalid CSRF token cannot initialize settings');
[$hidden] = settingsForm($settingsJar);
$response = settingsRequest('POST', $settingsJar, $hidden + array_diff_key($values, $hidden));
check($response['status'] === 303, 'Valid Form API submission redirects after saving');
$expected = ['enabled' => TRUE, 'query_policy' => ['timezone' => 'Asia/Shanghai',
  'languages' => ['en', 'fr'], 'max_calendar_months' => 12, 'max_scanned_entities' => 5000,
  'max_groups' => 100, 'max_seconds' => 10]];
check(savedSettings() === $expected, 'Saved settings preserve boolean, integer and language-list types');

$invalid = [
  ['max_calendar_months' => '0'], ['max_calendar_months' => '121'],
  ['max_scanned_entities' => '9007199254740992'], ['max_scanned_entities' => '1.5'],
  ['max_groups' => '101'], ['max_seconds' => '61'], ['max_seconds' => '1e1'],
  ['languages' => []], ['languages' => ['zz' => 'zz']], ['timezone' => 'Mars/Olympus'],
];
foreach ($invalid as $change) {
  [$hidden] = settingsForm($settingsJar);
  $response = settingsRequest('POST', $settingsJar, $hidden + ['enabled' => '1',
    'query_policy' => array_replace($policy, $change), 'op' => 'Save configuration']);
  check($response['status'] === 200, 'Invalid settings return the form: ' . json_encode($change));
  check(savedSettings() === $expected, 'Invalid settings do not mutate saved configuration');
}

[$hidden, $xpath] = settingsForm($settingsJar);
check($xpath->query('//input[@name="enabled" and @checked]')->length === 1, 'Saved enablement survives reload');
$discovery = status(http('GET', '/api/v3/analytics/datasets?language=en', $tokens['full']), 200, 'Discovery uses saved form settings');
$automatic = array_values(array_filter($discovery['datasets'], fn(array $dataset) => $dataset['id'] === 'node__article'));
check(count($automatic) === 1 && $automatic[0]['timezone'] === 'Asia/Shanghai', 'Form settings configure the actual automatic source');
$automaticQuery = ['datasetId' => 'node__article', 'datasetVersion' => $automatic[0]['version'],
  'dimensions' => [], 'filters' => [], 'scope' => ['kind' => 'all'], 'timezone' => 'Asia/Shanghai', 'language' => 'en'];
$receipt = status(http('POST', '/api/v3/analytics/evidence', $tokens['full'],
  body: json_encode($automaticQuery)), 200, 'Source accepts the form policy and counts');
settingsForm($settingsJar);
check(status(http('POST', '/api/v3/analytics/evidence/verify', $tokens['full'],
  body: json_encode($receipt)), 200, 'Read-only settings view preserves evidence')['valid'], 'Viewing settings does not invalidate receipts');

$policy['timezone'] = 'UTC';
$policy['languages'] = ['en' => 'en'];
$policy['max_calendar_months'] = '6';
[$hidden] = settingsForm($settingsJar);
check(settingsRequest('POST', $settingsJar, $hidden + ['enabled' => '1', 'query_policy' => $policy,
  'op' => 'Save configuration'])['status'] === 303, 'Administrator can change existing settings');
check(savedSettings()['query_policy']['languages'] === ['en']
  && savedSettings()['query_policy']['max_calendar_months'] === 6, 'Changes are saved without restoring initial suggestions');
status(http('POST', '/api/v3/analytics/evidence/verify', $tokens['full'],
  body: json_encode($receipt)), 410, 'Saving settings invalidates old source evidence');
[$hidden, $xpath] = settingsForm($settingsJar);
check($xpath->query('//input[@name="query_policy[max_calendar_months]" and @value="6"]')->length === 1,
  'Reload shows the custom value');
check(settingsRequest('POST', $settingsJar, $hidden + ['query_policy' => $policy,
  'op' => 'Save configuration'])['status'] === 303, 'Administrator can disable automatic counts');
check(savedSettings()['enabled'] === FALSE, 'Disablement saves a boolean false');
$discovery = status(http('GET', '/api/v3/analytics/datasets?language=en', $tokens['full']), 200, 'Discovery after form disablement');
check(array_filter($discovery['datasets'], fn(array $dataset) => str_starts_with($dataset['id'], 'node__')) === [],
  'Disabled automatic sources disappear without removing configured datasets');
check(array_filter($discovery['datasets'], fn(array $dataset) => $dataset['id'] === 'editorial_activity') !== [],
  'Configured datasets retain their independent policy');
\Drupal::configFactory()->getEditable('xinshi_analytics.node_counts')->setData($originalSettings)->save();
