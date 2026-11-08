<?php

/**
 * @file
 * Ordinary Chat access through actual Drupal readers, including cached field Views.
 */

use Drupal\node\Entity\Node;
use Drupal\user\Entity\Role;
use Drupal\views\Entity\View;

function checkReadIsolation(array $users, array $tokens, string $jar): void {
  Role::load('anonymous')->grantPermission('access content')->save();
  $private = Node::create(['type' => 'ai_session', 'title' => 'Private original marker',
    'uid' => $users['owner']->id(), 'status' => 1, 'session_role' => 'assistant',
    'storage_version' => 3, 'edit_version' => 1, 'storage_mode' => 'inline',
    'content' => ['value' => 'Private source marker', 'format' => 'plain_text']]);
  $private->save();
  $original_revision = $private->getRevisionId();
  $private->setNewRevision(TRUE);
  $private->save();
  $chat = Node::create(['type' => 'conversation', 'title' => 'Private conversation marker',
    'uid' => $users['owner']->id(), 'status' => 1, 'sessions' => [['target_id' => $private->id()]]]);
  $chat->save();
  $public = Node::create(['type' => 'project', 'title' => 'Public project marker',
    'uid' => $users['owner']->id(), 'status' => 1]);
  $public->save();
  View::create(['id' => 'chat_read_test', 'label' => 'Chat read boundary',
    'base_table' => 'node_field_data', 'base_field' => 'nid', 'status' => TRUE,
    'display' => ['default' => ['id' => 'default', 'display_plugin' => 'default', 'display_options' => [
      'access' => ['type' => 'none'], 'cache' => ['type' => 'time', 'options' => ['results_lifespan' => 3600, 'output_lifespan' => 3600]],
      'pager' => ['type' => 'none'],
      'fields' => ['title' => ['id' => 'title', 'table' => 'node_field_data', 'field' => 'title', 'plugin_id' => 'field']],
    ]], 'rest_export_1' => ['id' => 'rest_export_1', 'display_plugin' => 'rest_export', 'display_options' => [
      'path' => 'chat-read-test', 'style' => ['type' => 'serializer'], 'row' => ['type' => 'data_field'],
      'pager' => ['type' => 'none', 'options' => ['offset' => 0]],
      'auth' => ['oauth2'], 'defaults' => ['style' => FALSE, 'row' => FALSE],
    ]]],
  ])->save();
  \Drupal::service('router.builder')->rebuild();
  $chat_path = '/jsonapi/node/conversation/' . $chat->uuid();
  $message_path = '/jsonapi/node/ai_session/' . $private->uuid();
  foreach (['owner' => 200, 'admin' => 200, 'other' => 403, 'anonymous' => 403, 'limited' => 403, 'blocked' => 401] as $reader => $status) {
    foreach ([$message_path, $chat_path, $message_path . '?fields[node--ai_session]=title,content',
      $message_path . '?resourceVersion=id:' . $private->getRevisionId(),
      $message_path . '?resourceVersion=id:' . $original_revision,
      $chat_path . '/relationships/sessions', $chat_path . '/sessions', $chat_path . '?include=sessions'] as $path) {
      $response = http($path, $tokens[$reader] ?? NULL);
      check($response['status'] === $status, $reader . ' direct/sparse/revision/relationship access: ' . $path . ($response['status'] === $status ? '' : ' => ' . json_encode($response)));
      if ($status !== 200) {
        check(!isset($response['data']['data']) && !isset($response['data']['included']), 'Denied reads have no resource body');
      }
    }
    if (in_array($reader, ['owner', 'admin', 'other', 'anonymous'], TRUE)) {
      foreach (['ai_session', 'conversation'] as $bundle) {
        $list = http('/jsonapi/node/' . $bundle, $tokens[$reader] ?? NULL);
        check($list['status'] === 200, $reader . ' can query collection');
        $ids = array_column($list['data']['data'], 'id');
        check(in_array($bundle === 'ai_session' ? $private->uuid() : $chat->uuid(), $ids, TRUE) ===
          in_array($reader, ['owner', 'admin'], TRUE), $reader . ' collection excludes private rows before pagination');
      }
    }
  }
  check(http($message_path, NULL, 'GET', NULL, $jar)['status'] === 200, 'Owner cookie retains ordinary message reads');
  $other_jar = '/tmp/chat-test/other.cookie';
  http('/user/login?_format=json', NULL, 'POST', ['name' => 'chat-http-other', 'pass' => 'test-only-chat'], $other_jar);
  check(http($message_path, NULL, 'GET', NULL, $other_jar)['status'] === 403, 'Other cookie cannot read a message');
  check(http('/node/' . $private->id(), NULL, 'GET', NULL, $jar, FALSE)['status'] === 200,
    'The author can still view the ordinary node route');
  foreach ([$other_jar, NULL] as $reader_jar) {
    check(http('/node/' . $private->id(), NULL, 'GET', NULL, $reader_jar, FALSE)['status'] === 403,
      'A warm ordinary node route does not expose another author title or body');
  }
  foreach (['owner', 'other', 'anonymous', 'admin', 'other'] as $reader) {
    $view = http('/chat-read-test?_format=json', $tokens[$reader] ?? NULL, check_cache: FALSE);
    check($view['status'] === 200, 'Cached field View returns successfully: ' . ($view['status'] === 200 ? '' : json_encode($view)));
    $values = json_encode($view['data']);
    check(str_contains($values, 'Public project marker'), 'Public unrelated content remains visible to ' . $reader);
    check(str_contains($values, 'Private original marker') === in_array($reader, ['owner', 'admin'], TRUE),
      'Cached field View preserves the ' . $reader . ' boundary');
  }
  foreach (['node_field_data', 'node_field_revision'] as $table) {
    foreach (['owner', 'other'] as $reader) {
      $query = \Drupal::database()->select($table, 'n')->fields('n', ['nid'])->addTag('node_access')
        ->addMetaData('account', $users[$reader])->condition('n.nid', $private->id());
      check((bool) $query->execute()->fetchField() === ($reader === 'owner'),
        'Tagged ' . $table . ' query respects the supplied effective account');
    }
  }
  $chat->delete();
  $private->delete();
  $public->delete();
  View::load('chat_read_test')->delete();
}
