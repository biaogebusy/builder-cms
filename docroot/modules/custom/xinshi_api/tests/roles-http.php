<?php

declare(strict_types=1);

/**
 * @file
 * Extends the isolated HTTP runner with role-list permissions and OAuth scopes.
 */

use Drupal\node\Entity\NodeType;
use Drupal\simple_oauth\Entity\Oauth2Scope;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

if (getenv('XINSHI_API_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') ||
    !isset($fixture, $admin, $ordinary, $tokens) || $fixture !== '/tmp/xinshi-api-test') {
  throw new RuntimeException('Run through the disposable auth-http-smoke.php fixture only.');
}
check(!\Drupal::moduleHandler()->moduleExists('xinshi_ai'), 'Role discovery works with the AI module disabled');

// The route uses the existing branding node permission, not role administration.
if (!NodeType::load('json')) {
  NodeType::create(['type' => 'json', 'name' => 'Branding JSON'])->save();
}
Role::create(['id' => 'http_branding', 'label' => 'Branding editor', 'permissions' => [
  'edit any json content',
]])->save();
Oauth2Scope::create([
  'name' => 'http_branding', 'granularity_id' => 'role',
  'granularity_configuration' => ['role' => 'http_branding'],
  'grant_types' => ['authorization_code' => ['status' => TRUE]],
])->save();
$password = bin2hex(random_bytes(24));
$editor = User::create([
  'name' => 'http-branding-editor', 'pass' => $password, 'status' => 1,
  'roles' => ['http_author', 'http_branding'],
]);
$editor->save();
check((int) $editor->id() !== 1 && !$editor->hasPermission('administer permissions'),
  'Branding editor has no UID 1 or role-administration bypass');
$jar = $fixture . '/branding.cookies';
touch($jar);
chmod($jar, 0600);
status(http('POST', '/user/login?_format=json', $jar, body: json_encode([
  'name' => $editor->getAccountName(), 'pass' => $password,
], JSON_THROW_ON_ERROR)), 200, 'Branding editor authenticates through Cookie login');
$editor_token = issueToken((string) $editor->id(), scope: 'http_branding');
$narrow_token = issueToken((string) $editor->id());
$path = '/api/v3/roles';
$response = status(http('GET', $path, $jar), 200, 'Branding permission allows Cookie GET without CSRF');
$data = json_decode($response['body'], TRUE, 512, JSON_THROW_ON_ERROR);
check(array_is_list($data), 'Role list is a flat JSON array');
check(!in_array('anonymous', array_column($data, 'id'), TRUE), 'Unmatchable anonymous role is excluded');
$listed = array_column($data, NULL, 'id');
check($listed['http_branding'] === ['id' => 'http_branding', 'label' => 'Branding editor'],
  'Role list exposes the machine name and label only');
status(http('GET', $path, bearer: $editor_token), 200, 'Branding OAuth scope allows role discovery');
status(http('GET', $path, bearer: $narrow_token), 403, 'Narrow OAuth scope cannot borrow the user branding role');
status(http('GET', $path, $admin['jar']), 200, 'Administrator Cookie keeps role discovery access');
status(http('GET', $path), 403, 'Cached role discovery does not grant anonymous access');
status(http('GET', $path, $ordinary['jar']), 403, 'Ordinary Cookie cannot discover roles');
status(http('GET', $path, bearer: $tokens['ordinary']), 403, 'Ordinary OAuth cannot discover roles');
status(http('GET', $path, $jar, $tokens['ordinary']), 403, 'Ordinary Bearer cannot borrow a branding Cookie');
status(http('GET', $path, $ordinary['jar'], $editor_token), 200, 'Branding Bearer takes priority over ordinary Cookie');
status(http('POST', $path, bearer: $editor_token), 405, 'Role discovery is read-only');
foreach (['blocked', 'expired', 'revoked', 'malformed'] as $name) {
  status(http('GET', $path, $admin['jar'], $tokens[$name] ?? 'not-a-valid-jwt'), 401,
    'Role discovery rejects unusable Bearer despite valid Cookie: ' . $name);
}
