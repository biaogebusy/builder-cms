<?php

use Xinshi\Migration\Runtime;

Runtime::requireEntryPoint();

/** Apply owner-ID mapping without replaying Webform alias creation hooks. */
use Drupal\Core\Database\Database;

if ((Database::getConnection()->getConnectionOptions()['database'] ?? '') !== Runtime::targetDatabase()) {
  throw new RuntimeException('Only the prepared model may be adapted here.');
}
$collections = json_decode(file_get_contents(Runtime::path('model-config-plan.private.json')), TRUE, 512, JSON_THROW_ON_ERROR);
$mapped = [];
$destinationUid = (int) Runtime::plan()['source_admin_destination_uid'];
foreach ($collections[''] as $name => $data) {
  if (!str_starts_with($name, 'webform.webform.') || ($data['uid'] ?? NULL) !== $destinationUid) {
    continue;
  }
  $config = \Drupal::configFactory()->getEditable($name);
  if (!in_array((int) $config->get('uid'), [1, $destinationUid], TRUE)) {
    throw new RuntimeException('The form owner changed outside the prepared configuration.');
  }
  $config->set('uid', $destinationUid)->save();
  \Drupal::entityTypeManager()->getStorage('webform')->resetCache([$data['id']]);
  $mapped[] = $name;
}
print json_encode(['mapped_form_owners' => $mapped, 'destination_uid' => $destinationUid], JSON_THROW_ON_ERROR) . PHP_EOL;
