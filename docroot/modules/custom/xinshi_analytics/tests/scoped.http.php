<?php

declare(strict_types=1);

/**
 * @file
 * Included inside the disposable HTTP server lifecycle, using real OAuth scopes.
 */

$settings = \Drupal::configFactory()->getEditable('xinshi_analytics.node_counts');
$settings->set('enabled', TRUE)->set('query_policy', ['timezone' => 'UTC', 'languages' => ['en', 'fr'],
  'max_calendar_months' => 12, 'max_scanned_entities' => 500, 'max_groups' => 1, 'max_seconds' => 10])->save();
$base = '/api/v3/analytics';
$found = status(http('GET', $base . '/datasets?language=en', $tokens['scoped']), 200,
  'discovers automatic content types without any dataset grant');
$selected = array_values(array_filter($found['datasets'], fn($item) => $item['id'] === 'node__article'))[0];
$all = ['datasetId' => $selected['id'], 'datasetVersion' => $selected['version'], 'dimensions' => [],
  'filters' => [], 'scope' => ['kind' => 'all'], 'timezone' => 'UTC', 'language' => 'en'];
$allBody = json_encode($all, JSON_THROW_ON_ERROR);
$range = $all;
$range['scope'] = ['kind' => 'range'] + $query['scope'];
$allResult = status(http('POST', $base . '/count', $tokens['scoped'], body: $allBody), 200,
  'count retains the OAuth principal and needs no dataset-specific grant');
$rangeResult = status(http('POST', $base . '/count', $tokens['scoped'], body: json_encode($range)), 200,
  'range counts over the same authenticated endpoint');
check($allResult['rows'] === [['dimensions' => [], 'count' => 2]] && $rangeResult['rows'] === $allResult['rows'],
  'HTTP both modes respect node grants');
check(status(http('POST', $base . '/count', jar: $jar, body: $allBody), 200,
  'Cookie read-only count requires no write CSRF')['rows'] === $allResult['rows'], 'Cookie and OAuth count agree');
foreach (['count' => $allBody, 'evidence' => $allBody] as $path => $payload) {
  status(http('POST', $base . '/' . $path, body: $payload), 403, 'Anonymous request denied');
  status(http('POST', $base . '/' . $path, $tokens['none'], body: $payload), 403, 'Missing general query scope denied');
  status(http('POST', $base . '/' . $path, $tokens['general'], body: $payload), 403, 'Missing participating field scope denied');
  status(http('POST', $base . '/' . $path, $tokens['expired'], $jar, $payload), 401, 'Expired Bearer cannot borrow Cookie');
  status(http('POST', $base . '/' . $path, $tokens['blocked'], body: $payload), 401, 'Blocked OAuth account denied');
}
check(status(http('GET', $base . '/datasets?language=en', $tokens['general']), 200,
  'Scoped discovery checks field permissions')['datasets'] === [], 'Field-limited scope hides automatic metadata');
status(http('POST', $base . '/count', $tokens['scoped'], body: $body), 403, 'Configured counts still need their dataset grant');
$legacy = $all;
unset($legacy['scope']);
$legacy['range'] = array_diff_key($query['scope'], ['kind' => TRUE]);
status(http('POST', $base . '/count', $tokens['scoped'], body: json_encode($legacy)), 400, 'Range-only shape is obsolete');
status(http('POST', $count, $tokens['full'], body: $allBody), 200, 'Both source modes use one count endpoint');
status(http('POST', $base . '/count', $tokens['scoped'], body: str_replace('"filters":[]', '"filters":{}', $allBody)),
  400, 'retains JSON array distinctions');
status(http('POST', $base . '/count', $tokens['scoped'], body: json_encode($all + ['range' => $query['scope']])),
  400, 'rejects mixed time modes');
$automaticEvidence = status(http('POST', $base . '/evidence', $tokens['scoped'], body: $allBody), 200, 'HTTP evidence capture');
$automaticVerification = status(http('POST', $base . '/evidence/verify', $tokens['scoped'], body: json_encode($automaticEvidence)),
  200, 'HTTP evidence verification');
$changed = $automaticEvidence;
$changed['result']['query']['scope'] = $range['scope'];
status(http('POST', $base . '/evidence/verify', $tokens['scoped'], body: json_encode($changed)), 410,
  'Equal totals cannot authorize a substituted scope');
status(http('POST', $base . '/evidence/verify', $tokens['general'], body: json_encode($automaticEvidence)), 410,
  'Narrower OAuth field scope cannot replay an automatic report');
status(http('POST', $base . '/evidence/verify', $tokens['scoped'], body: json_encode(array_replace($sealed, ['version' => 1]))), 400,
  'Evidence endpoint refuses obsolete envelopes');
status(http('POST', $verifyPath, $tokens['full'], body: json_encode($automaticEvidence)), 200,
  'One evidence endpoint handles both source modes');
file_put_contents($fixture . '/scoped-http-protocol.json', json_encode([
  'discovery' => $found, 'all' => $allResult, 'range' => $rangeResult,
  'evidence' => $automaticEvidence, 'verification' => $automaticVerification,
], JSON_THROW_ON_ERROR));
$settings->set('enabled', FALSE)->save();
status(http('POST', $base . '/count', $tokens['scoped'], body: $allBody), 403, 'Disabled automatic source rejects HTTP counts');
status(http('POST', $base . '/evidence/verify', $tokens['scoped'], body: json_encode($automaticEvidence)), 410,
  'Disabled automatic source refuses old evidence');
$settings->delete();
