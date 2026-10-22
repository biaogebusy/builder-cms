<?php

declare(strict_types=1);

/**
 * @file
 * Administrative access and real Form API deletion checks in the HTTP fixture.
 */

use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

if (!isset($server, $tokens, $receipt) || getenv('XINSHI_OFFICE_ISOLATED_TEST') !== '1') {
  throw new RuntimeException('Use the Office HTTP fixture.');
}

function formValues(string $html): array {
  $dom = new DOMDocument();
  @$dom->loadHTML($html);
  $values = [];
  foreach ($dom->getElementsByTagName('input') as $input) {
    if ($input->hasAttribute('name')) $values[$input->getAttribute('name')] = $input->getAttribute('value');
  }
  return $values;
}
function adminHttp(string $method, string $path, ?string $jar = NULL, array $values = []): array {
  return http($method, $path, jar: $jar, body: http_build_query($values), type: 'application/x-www-form-urlencoded');
}

$admin_base = '/admin/content/office-documents';
$admin_path = $admin_base . '/' . $receipt['documentId'];
$admin_roles = ['viewer' => ['view all office documents'],
  'manager' => ['view all office documents', 'delete office documents'], 'delete_only' => ['delete office documents']];
$admin_jars = [];
$admin_users = [];
foreach ($admin_roles as $name => $permissions) {
  $role_id = 'office_admin_' . $name;
  Role::create(['id' => $role_id, 'label' => $role_id, 'permissions' => $permissions])->save();
  $account = User::create(['name' => $role_id, 'pass' => $password, 'status' => 1, 'roles' => [$role_id]]);
  $account->save();
  $admin_users[$name] = $account;
  $admin_jars[$name] = $fixture . '/' . $name . '.cookie';
  status(http('POST', '/user/login?_format=json', jar: $admin_jars[$name],
    body: json_encode(['name' => $account->getAccountName(), 'pass' => $password])), 200, 'Administrative Cookie login');
}
foreach ([$admin_base, $admin_path, $admin_path . '/snapshot/1', $admin_path . '/files/' . $file_id . '/3',
  $admin_path . '/delete', $admin_path . '/restore'] as $target) {
  check(adminHttp('GET', $target)['status'] === 403, 'Anonymous cannot access administration');
  check(adminHttp('GET', $target, $jar)['status'] === 403, 'Office owner grants do not grant administration');
  check(adminHttp('GET', $target, $admin_jars['delete_only'])['status'] === 403,
    'Delete permission alone cannot expose a document');
}
$viewer = $admin_jars['viewer'];
$manager = $admin_jars['manager'];
$listing = adminHttp('GET', $admin_base . '?uid=' . $user->id(), $viewer);
check($listing['status'] === 200 && str_contains($listing['body'], 'HTTP edited'), 'Viewer can list another owner’s documents');
check(!str_contains($listing['body'], $admin_path . '/delete'), 'View-only listing has no delete operation');
check(!str_contains(adminHttp('GET', $admin_base . '?name=absent-name', $viewer)['body'], 'HTTP edited'), 'Name filter excludes other documents');
check(!str_contains(adminHttp('GET', $admin_base . '?kind=sheet', $viewer)['body'], 'HTTP edited'), 'Type filter excludes Word');
check(adminHttp('GET', $admin_base . '?uid=bad', $viewer)['status'] === 400, 'Invalid owner filter rejected');
$detail = adminHttp('GET', $admin_path, $viewer);
check($detail['status'] === 200 && str_contains($detail['body'], 'HTTP 工作稿') && str_contains($detail['body'], 'Version history'),
  'Administrative detail renders content and versions');
$sheet_detail = adminHttp('GET', $admin_base . '/' . $sheet_receipt['documentId'], $viewer);
check($sheet_detail['status'] === 200 && str_contains($sheet_detail['body'], '=SUM(1,2)'), 'Spreadsheet preview retains the formula');
$snapshot = adminHttp('GET', $admin_path . '/snapshot/1', $viewer);
check($snapshot['status'] === 200 && $snapshot['data']['name'] === $input['name'], 'Historical JSON download preserves its own name');
$sheet_snapshot = adminHttp('GET', $admin_base . '/' . $sheet_receipt['documentId'] . '/snapshot/1', $viewer);
check(hash('sha256', $sheet_snapshot['body']) === $sheet_receipt['sha256'],
  'JSON download preserves exact bytes, empty objects and decimal representation');
$bytes = adminHttp('GET', $admin_path . '/files/' . $file_id . '/3', $viewer);
check($bytes['status'] === 200 && hash('sha256', $bytes['body']) === $file['sha256'], 'Administrative image download preserves exact bytes');
check(adminHttp('GET', $admin_path . '/files/' . $file_id . '/1', $viewer)['status'] === 404,
  'Administrator cannot download a file outside the selected revision');
check(adminHttp('GET', $admin_path . '/delete', $viewer)['status'] === 403, 'Viewer cannot open deletion form');
$confirmation = adminHttp('GET', $admin_path . '/delete', $manager);
check($confirmation['status'] === 200 && str_contains($confirmation['body'], 'HTTP edited'), 'Deletion requires a named confirmation page');
$values = formValues($confirmation['body']);
check(isset($values['form_token'], $values['expected_revision']), 'Confirmation contains CSRF and revision fields');
$invalid = $values;
unset($invalid['form_token']);
adminHttp('POST', $admin_path . '/delete', $manager, $invalid);
check(status(http('GET', $path, $tokens['full']), 200, 'Missing form token leaves document active')['revision'] === 3,
  'CSRF refusal does not change a revision');
$stale_update = $update;
$stale_update['operationId'] = \Drupal::service('uuid')->generate();
$stale_update['expectedRevision'] = 3;
$fourth = status(http('PATCH', $path, $tokens['full'], body: json_encode($stale_update)), 200, 'Owner edits while deletion form is open');
adminHttp('POST', $admin_path . '/delete', $manager, $values);
check(status(http('GET', $path, $tokens['full']), 200, 'Stale confirmation leaves document active')['revision'] === 4,
  'Stale confirmation cannot delete a newer revision');
$values = formValues(adminHttp('GET', $admin_path . '/delete', $manager)['body']);
check(adminHttp('POST', $admin_path . '/delete', $manager, $values)['status'] === 303, 'Valid confirmed deletion redirects to the trash');
status(http('GET', $path, $tokens['full']), 404, 'Trashed document cannot be opened as current');
check(!array_filter(status(http('GET', $base, $tokens['full']), 200, 'Active owner list')['items'],
  fn($item) => $item['documentId'] === $receipt['documentId']), 'Trashed document leaves owner discovery');
$new_update = $stale_update;
$new_update['operationId'] = \Drupal::service('uuid')->generate(); $new_update['expectedRevision'] = 4;
status(http('PATCH', $path, $tokens['full'], body: json_encode($new_update)), 404, 'New write to trash is refused');
check(status(http('GET', $base . '/operations/' . $stale_update['operationId'], $tokens['read']), 200,
  'Trash preserves receipt recovery') === $fourth, 'Original receipt is unchanged');
check(status(http('PATCH', $path, $tokens['full'], body: json_encode($stale_update)), 200,
  'Submitted write replay after trash') === $fourth, 'Exact replay does not execute another write');
check(status(http('GET', $path . '?revision=3', $tokens['read']), 200, 'Historical baseline after trash')['revision'] === 3,
  'Read-authorized reconciliation can still obtain its historical baseline');
status(http('GET', $path . '?revision=3', $tokens['other']), 404, 'Trash does not widen historical owner access');
check(str_contains(adminHttp('GET', $admin_base . '?deleted=1', $viewer)['body'], 'HTTP edited'), 'Administrator can discover the trash');
$restore = formValues(adminHttp('GET', $admin_path . '/restore', $manager)['body']);
check(adminHttp('POST', $admin_path . '/restore', $manager, $restore)['status'] === 303, 'Confirmed restore succeeds');
check(status(http('GET', $path, $tokens['full']), 200, 'Owner can reopen restored document')['revision'] === 4,
  'Restore preserves content revision');
$confirm_revoke = formValues(adminHttp('GET', $admin_path . '/delete', $manager)['body']);
Role::load('office_admin_manager')->revokePermission('delete office documents')->save();
check(adminHttp('POST', $admin_path . '/delete', $manager, $confirm_revoke)['status'] === 403,
  'Permission revocation invalidates an open deletion form');
$admin_users['viewer']->block()->save();
check(adminHttp('GET', $admin_path, $viewer)['status'] === 403, 'Blocked administrator cannot read private documents');

// Existing-site installation adds only lifecycle metadata, preserving saved payload bytes.
$before = \Drupal::database()->select('xinshi_office_revision', 'r')->fields('r')->execute()->fetchAll(\PDO::FETCH_ASSOC);
\Drupal::moduleHandler()->loadInclude('xinshi_office', 'install');
$schema = \Drupal::database()->schema();
$schema->dropIndex('xinshi_office_document', 'admin_documents');
$schema->dropField('xinshi_office_document', 'deleted');
xinshi_office_update_11001();
xinshi_office_update_11001();
check($before === \Drupal::database()->select('xinshi_office_revision', 'r')->fields('r')->execute()->fetchAll(\PDO::FETCH_ASSOC),
  'Existing-site update is repeatable and preserves every revision byte');
