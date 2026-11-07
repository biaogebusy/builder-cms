<?php

/**
 * @file
 * Translation regressions included by the disposable auth-http-smoke.php runner.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\simple_oauth\Entity\Oauth2Scope;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

if (getenv('XINSHI_API_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') ||
    !isset($accounts, $sessions, $server) ||
    \Drupal::database()->getConnectionOptions()['database'] !== '/tmp/xinshi-api-test/site.sqlite') {
  throw new RuntimeException('Use the disposable auth-http-smoke.php runner only.');
}
\Drupal::service('module_installer')->install(['content_translation', 'path']);
ConfigurableLanguage::createFromLangcode('zh-hans')->save();
foreach ([['node', 'landing_page'], ['block_content', 'json']] as [$type, $bundle]) {
  \Drupal::service('content_translation.manager')->setEnabled($type, $bundle, TRUE);
}
foreach ([['node', 'landing_page', 'layout_builder__layout'], ['block_content', 'json', 'body']] as [$type, $bundle, $field]) {
  FieldStorageConfig::loadByName($type, $field)->setTranslatable(TRUE)->save();
  FieldConfig::loadByName($type, $bundle, $field)->setTranslatable(TRUE)->save();
}
\Drupal::service('router.builder')->rebuild();

// The administrator has no UID 1 bypass and requests a role scope without any
// translation permission, matching the reported account/scope combination.
check((int) $accounts['admin']['uid'] !== 1 && Role::load('http_admin')->isAdmin(),
  'Translation administrator uses an actual administrator role, not UID 1');
check(!array_filter(Role::load('http_author')->getPermissions(), fn($p) => str_contains($p, 'translat')),
  'Requested role scope has no translation permissions');
$adminToken = issueToken((string) $accounts['admin']['uid']);
$ordinaryToken = issueToken((string) $accounts['ordinary']['uid']);
Role::create(['id' => 'translator', 'label' => 'Translator', 'permissions' => [
  'access content', 'edit any landing_page content',
  'create content translations', 'translate landing_page node', 'create url aliases',
]])->save();
$translator = User::create(['name' => 'page-translator', 'status' => 1, 'roles' => ['translator']]);
$translator->save();
Oauth2Scope::create(['name' => 'translator', 'granularity_id' => 'role',
  'granularity_configuration' => ['role' => 'translator'],
  'grant_types' => ['authorization_code' => ['status' => TRUE]],
])->save();
$translatorToken = issueToken((string) $translator->id(), scope: 'translator');

// Preserve the reported request shape: no outer UUID, nested metadata, full
// custom-template HTML/CSS and a null JS field. Assets are never fetched.
$translatedBody = [
  'extra' => ['uuid' => '403071a7-bfc6-4d79-b091-18742ef30746', 'id' => '1753', 'type' => 'json'],
  'fullWidth' => FALSE, 'spacer' => 'none', 'containerClasses' => '',
  'bg' => ['classes' => '', 'overlay' => '', 'img' => ['src' => '', 'alt' => '']],
  'isAPI' => FALSE, 'dialogs' => [], 'animate' => ['aos' => ['enable' => FALSE]],
  'type' => 'custom-template', 'api' => '',
  'html' => "<style>\n.tp-feature{background:#222220;}\n@media(max-width:959px){.tp-feature{padding:16px;}}\n</style>\n<section class=\"tp-feature\"><img src=\"https://example.test/asset\" alt=\"Original alt\" /><h2>Europe&rsquo;s Bid for AI Dominance</h2></section>",
  'js' => NULL,
];
$input = ['title' => 'Figma - AI生成', 'body' => [['type' => 'json', 'attributes' => ['body' => $translatedBody]]]];
$sourceBody = ['type' => 'custom-template', 'html' => '<section>保留原文</section>', 'js' => NULL];

function translationPage(array $body, string $owner): Node {
  \Drupal::currentUser()->setAccount(User::load($GLOBALS['accounts']['admin']['uid']));
  $page = \Drupal::service('xinshi_api.page_writer')->createPage(['title' => '中文原页',
    'body' => [['type' => 'json', 'attributes' => ['body' => $body]]]]);
  $page->set('langcode', 'zh-hans');
  $page->setOwnerId($owner);
  $page->set('path', ['alias' => '/translation-source-' . $page->id()]);
  $page->save();
  $block = translationBlock($page);
  $block->set('langcode', 'zh-hans')->save();
  \Drupal::entityTypeManager()->getStorage('node')->resetCache([$page->id()]);
  return Node::load($page->id());
}

function translationBlock(Node $page): \Drupal\block_content\BlockContentInterface {
  $components = $page->get('layout_builder__layout')->getSections()[0]->getComponents();
  $revision = reset($components)->get('configuration')['block_revision_id'];
  $storage = \Drupal::entityTypeManager()->getStorage('block_content');
  $storage->resetCache();
  $block = $storage->loadRevision($revision);
  return $block->hasTranslation($page->language()->getId()) ? $block->getTranslation($page->language()->getId()) : $block;
}

function translationCounts(): array {
  $counts = [];
  foreach (['node', 'node_revision', 'block_content', 'block_content_revision'] as $table) {
    $counts[$table] = (int) \Drupal::database()->select($table)->countQuery()->execute()->fetchField();
  }
  return $counts;
}

$translationPath = '/api/v3/landingPage/translations/add/';
foreach ([
  'administrator Bearer' => ['bearer' => $adminToken],
  'administrator Cookie' => ['jar' => $admin['jar'], 'csrf' => $admin['csrf']],
  'ordinary translator Bearer' => ['bearer' => $translatorToken],
  'copy source components' => ['bearer' => $translatorToken],
] as $label => $auth) {
  $page = translationPage($sourceBody, (string) $accounts['ordinary']['uid']);
  $source = $page->toArray();
  $sourceBlock = translationBlock($page)->toArray();
  $copy = $label === 'copy source components';
  $response = http('POST', $translationPath . $page->id() . '/zh-hans/en',
    ...($auth + ['body' => json_encode($copy ? new stdClass() : $input, JSON_THROW_ON_ERROR)]));
  check($response['status'] === 200, $label . ' creates translation: ' . $response['body']);
  check(json_decode($response['body'], TRUE)['status'] === TRUE, $label . ' reports a successful write');
  \Drupal::entityTypeManager()->getStorage('node')->resetCache([$page->id()]);
  $saved = Node::load($page->id());
  check($saved->hasTranslation('en'), $label . ' persists the English translation');
  $english = $saved->getTranslation('en');
  check($english->label() === ($copy ? '中文原页' : $input['title']), $label . ' preserves the requested title');
  check(json_decode(translationBlock($english)->get('body')->value, TRUE) === ($copy ? $sourceBody : $translatedBody),
    $label . ' preserves all component content');
  $after = $saved->getTranslation('zh-hans')->toArray();
  // A translation save advances the shared revision metadata, not source fields.
  foreach (['title', 'uid', 'status', 'path', 'layout_builder__layout'] as $field) {
    check($after[$field] == $source[$field], $label . ' preserves source ' . $field);
  }
  check(translationBlock($saved)->get('body')->value === $sourceBlock['body'][0]['value'],
    $label . ' preserves the original component body');
}

$page = translationPage($sourceBody, (string) $accounts['ordinary']['uid']);
$before = translationCounts();
$response = status(http('POST', $translationPath . $page->id() . '/zh-hans/en',
  bearer: $ordinaryToken, body: json_encode($input)), 403, 'Page editor without translation permission is refused');
$data = json_decode($response['body'], TRUE);
check($data['code'] === 'permission_denied' && $data['detail'] === 'page_translation_denied',
  'HTTP refusal identifies translation authorization');
check(!array_diff(array_keys($data), ['status', 'code', 'message', 'detail']) &&
  !str_contains($response['body'], 'tp-feature'), 'Diagnostic exposes no input or internal exception');
\Drupal::entityTypeManager()->getStorage('node')->resetCache([$page->id()]);
check(translationCounts() === $before && !Node::load($page->id())->hasTranslation('en'),
  'Refused translation creates no entity, revision or partial translation');
echo "Isolated page translation HTTP checks passed.\n";
