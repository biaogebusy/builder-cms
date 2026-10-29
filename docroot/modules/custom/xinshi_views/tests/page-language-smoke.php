<?php

declare(strict_types=1);

/**
 * @file
 * Verifies sparse JSON:API identity in the disposable page integration site.
 */

use Drupal\Core\DrupalKernel;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\Role;
use Symfony\Component\HttpFoundation\Request;

$root = dirname(__DIR__, 5);
if (getenv('XINSHI_API_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') ||
    $root !== '/app' || !is_file('/tmp/xinshi-api-test/site.sqlite')) {
  throw new RuntimeException('Use the disposable integration-smoke.php site only.');
}
require $root . '/docroot/modules/custom/xinshi_api/tests/bootstrap.php';
chdir($root . '/docroot');
$mode = $argv[1] ?? 'setup';
if ($mode === 'setup') {
  $request = Request::create('http://api-test/');
  $kernel = DrupalKernel::createFromRequest($request, $loader, 'prod');
  $kernel->boot();
  $kernel->preHandle($request);
  \Drupal::service('module_installer')->install(['jsonapi', 'content_translation']);
  if (!ConfigurableLanguage::load('zh-hans')) {
    ConfigurableLanguage::createFromLangcode('zh-hans')->save();
  }
  \Drupal::configFactory()->getEditable('system.site')->set('default_langcode', 'zh-hans')->save();
  \Drupal::configFactory()->getEditable('language.negotiation')->set('url.prefixes', [
    'en' => 'en', 'zh-hans' => '', 'fr' => 'fr',
  ])->save();
  \Drupal::service('language_negotiator')->updateConfiguration(['language_interface', 'language_content']);
  Role::load('anonymous')->grantPermission('access content')->save();
  $page = Node::create(['type' => 'landing_page', 'title' => 'English original',
    'status' => 1, 'langcode' => 'en', 'uid' => 1, 'moderation_state' => 'published']);
  $page->addTranslation('zh-hans', ['title' => 'Chinese translation', 'status' => 1,
    'moderation_state' => 'published']);
  $page->save();
  file_put_contents('/tmp/xinshi-api-test/language-page.json', json_encode([
    'uuid' => $page->uuid(), 'nid' => $page->id(),
  ], JSON_THROW_ON_ERROR));
  echo 'PASS: Created an English original on a Chinese-default test site.' . PHP_EOL;
  exit;
}
$identity = json_decode(file_get_contents('/tmp/xinshi-api-test/language-page.json'), TRUE, 512, JSON_THROW_ON_ERROR);
$prefix = $mode === 'en' ? '/en' : '';
$request = Request::create('http://api-test' . $prefix . '/jsonapi/node/landing_page/' . $identity['uuid'] . '?' . http_build_query([
  'fields' => ['node--landing_page' => 'langcode,default_langcode,drupal_internal__nid'],
  'noCache' => time(),
]));
$request->headers->set('Accept', 'application/vnd.api+json');
$kernel = DrupalKernel::createFromRequest($request, $loader, 'prod');
$response = $kernel->handle($request);
$data = json_decode($response->getContent(), TRUE, 512, JSON_THROW_ON_ERROR)['data'] ?? [];
$attributes = $data['attributes'] ?? [];
if ($response->getStatusCode() !== 200 || ($data['id'] ?? '') !== $identity['uuid'] ||
    ($attributes['langcode'] ?? '') !== $mode ||
    ($attributes['default_langcode'] ?? NULL) !== ($mode === 'en') ||
    (string) ($attributes['drupal_internal__nid'] ?? '') !== (string) $identity['nid']) {
  throw new RuntimeException('Sparse language identity failed: ' . $mode . ' ' . $response->getContent());
}
echo 'PASS: Sparse JSON:API returns the exact ' . $mode . ' identity and original flag.' . PHP_EOL;
$kernel->terminate($request, $response);
