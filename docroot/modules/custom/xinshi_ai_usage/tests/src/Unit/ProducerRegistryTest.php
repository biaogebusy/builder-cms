<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai_usage\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueMemoryFactory;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\user\Access\PermissionAccessCheck;
use Drupal\xinshi_ai_usage\Form\ProducerSettingsForm;
use Drupal\xinshi_ai_usage\Service\ProducerIdentity;
use Drupal\xinshi_ai_usage\Service\ProducerVault;
use Drupal\xinshi_ai_usage\Service\ServiceAuthException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Route;
use Symfony\Component\Yaml\Yaml;

/** Admin-registered producers: encrypted storage, one-time secrets and precedence rules. */
final class ProducerRegistryTest extends TestCase {

  private const PATH = '/api/v3/ai/metering/events';

  private KeyValueMemoryFactory $keys;
  private ProducerVault $vault;
  private ProducerIdentity $identity;
  private ProducerSettingsForm $form;
  private array $messages = [];
  private int $now = 1_700_000_000;

  protected function setUp(): void {
    parent::setUp();
    $this->keys = new KeyValueMemoryFactory();
    $privateKey = $this->createMock(PrivateKey::class);
    $privateKey->method('get')->willReturn('isolated-site-private-key');
    $this->vault = new ProducerVault($this->keys, $privateKey);
    $settings = new Settings([
      'hash_salt' => 'isolated-hash-salt',
      'xinshi_ai_usage.producers' => ['deploy-node' => ['secret' => 'deploy-secret', 'site_id' => 'deploy-site']],
    ]);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturnCallback(fn() => $this->now);
    $nonces = [];
    $nonceStore = $this->createMock(KeyValueStoreExpirableInterface::class);
    $nonceStore->method('setWithExpireIfNotExists')->willReturnCallback(function ($key) use (&$nonces): bool {
      if (isset($nonces[$key])) {
        return FALSE;
      }
      $nonces[$key] = TRUE;
      return TRUE;
    });
    $expirable = $this->createMock(KeyValueExpirableFactoryInterface::class);
    $expirable->method('get')->willReturn($nonceStore);
    $this->identity = new ProducerIdentity($settings, $expirable, $time, $this->vault);

    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translateString')->willReturnCallback(
      fn(TranslatableMarkup $markup) => $markup->getUntranslatedString());
    $messenger = $this->createMock(MessengerInterface::class);
    foreach (['addStatus', 'addWarning', 'addError'] as $method) {
      $messenger->method($method)->willReturnCallback(function ($message) use ($method, $messenger) {
        $this->messages[] = [$method, (string) $message];
        return $messenger;
      });
    }
    $container = new ContainerBuilder();
    $container->set('string_translation', $translation);
    $container->set('messenger', $messenger);
    \Drupal::setContainer($container);
    $requests = new RequestStack();
    $requests->push(Request::create('https://site-a.test/admin/config/xinshi/ai/usage'));
    $formatter = $this->createMock(DateFormatterInterface::class);
    $formatter->method('format')->willReturn('formatted-date');
    $this->form = new ProducerSettingsForm($this->vault, $settings, $requests, $time, $formatter);
  }

  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
  }

  public function testVaultStoresOnlyCiphertextAndIgnoresUnreadableEntries(): void {
    $this->vault->set('chat-node', 'plain-secret', 'site-a', 1_700_000_000);
    $stored = $this->keys->get('xinshi_ai_usage.producers')->get('chat-node');
    $this->assertIsString($stored);
    $this->assertStringNotContainsString('plain-secret', $stored);
    $this->assertStringNotContainsString('site-a', base64_decode($stored));
    $this->assertSame(['secret' => 'plain-secret', 'site_id' => 'site-a', 'created_at' => 1_700_000_000],
      $this->vault->get('chat-node'));
    // Ciphertext bound to another producer ID, or damaged, never yields a secret.
    $this->keys->get('xinshi_ai_usage.producers')->set('impostor', $stored);
    $this->keys->get('xinshi_ai_usage.producers')->set('damaged', 'not-base64!');
    $this->assertNull($this->vault->get('impostor'));
    $this->assertNull($this->vault->get('damaged'));
    $this->assertSame(['chat-node'], array_keys($this->vault->all()));
    $this->vault->delete('chat-node');
    $this->assertSame([], $this->vault->all());
    $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', ProducerVault::generateSecret());
    $this->assertNotSame(ProducerVault::generateSecret(), ProducerVault::generateSecret());
  }

  public function testIdentityAcceptsRegistryProducersAndLetsSettingsOverrideThem(): void {
    $body = '{"events":[]}';
    $this->assertSame(401, $this->authStatus($body, 'chat-node', 'ui-secret'));
    $this->vault->set('chat-node', 'ui-secret', 'site-a', $this->now);
    $this->assertSame(['producer_id' => 'chat-node', 'site_id' => 'site-a'],
      $this->identity->authenticate($this->signed($body, 'chat-node', 'ui-secret')));
    // settings.php keeps working and wins for a duplicated ID, so a UI entry cannot hijack it.
    $this->assertSame('deploy-site', $this->identity->authenticate($this->signed($body, 'deploy-node', 'deploy-secret'))['site_id']);
    $this->vault->set('deploy-node', 'ui-secret', 'site-b', $this->now);
    $this->assertSame(401, $this->authStatus($body, 'deploy-node', 'ui-secret'));
    $this->assertSame('deploy-site', $this->identity->authenticate($this->signed($body, 'deploy-node', 'deploy-secret'))['site_id']);
    // Rotation and deletion take effect immediately.
    $this->vault->set('chat-node', 'rotated', 'site-a', $this->now);
    $this->assertSame(401, $this->authStatus($body, 'chat-node', 'ui-secret'));
    $this->assertSame(200, $this->authStatus($body, 'chat-node', 'rotated'));
    $this->vault->delete('chat-node');
    $this->assertSame(401, $this->authStatus($body, 'chat-node', 'rotated'));
  }

  public function testFormShowsTheSecretOnceAndRotatesOrDeletesEntries(): void {
    $form = $this->form->buildForm([], new FormState());
    $this->assertSame('chat-node', $form['register']['producer_id']['#default_value']);
    $this->assertSame('site-a.test', $form['register']['site_id']['#default_value']);
    $this->assertArrayHasKey('deploy-node', $form['producers']);
    $this->assertArrayNotHasKey('#type', $form['producers']['deploy-node']['delete']);

    $state = (new FormState())->setValues(['producer_id' => 'chat-node', 'site_id' => 'site-a.test']);
    $this->form->validateForm($form, $state);
    $this->assertSame([], $state->getErrors());
    $this->form->submitForm($form, $state);
    $first = $this->vault->get('chat-node');
    $this->assertSame('site-a.test', $first['site_id']);
    $this->assertSame($this->now, $first['created_at']);
    $this->assertCount(1, $this->messages);
    [$level, $message] = $this->messages[0];
    $this->assertSame('addStatus', $level);
    $this->assertStringContainsString('METERING_INGEST_SECRET=' . $first['secret'], $message);
    $this->assertStringContainsString('METERING_PRODUCER_ID=chat-node', $message);
    $this->assertStringContainsString('METERING_SITE_ID=site-a.test', $message);

    // The rebuilt form lists the producer without its secret and offers deletion.
    $form = $this->form->buildForm([], new FormState());
    $this->assertSame('submit', $form['producers']['chat-node']['delete']['#type']);
    $this->assertSame('formatted-date', $form['producers']['chat-node']['created']['#plain_text']);
    $this->assertStringNotContainsString($first['secret'], json_encode($form));

    $this->form->submitForm($form, $state);
    $rotated = $this->vault->get('chat-node');
    $this->assertNotSame($first['secret'], $rotated['secret']);
    $this->assertStringContainsString($rotated['secret'], $this->messages[1][1]);

    // Registering an ID that settings.php already defines warns that it stays overridden.
    $this->form->submitForm($form, (new FormState())->setValues(['producer_id' => 'deploy-node', 'site_id' => 'x']));
    $this->assertSame('addWarning', end($this->messages)[0]);

    $delete = new FormState();
    $delete->setTriggeringElement(['#producer_id' => 'chat-node']);
    $this->form->deleteProducer($form, $delete);
    $this->assertNull($this->vault->get('chat-node'));
    $this->form->deleteProducer($form, $delete);
    $this->assertSame('addStatus', end($this->messages)[0]);

    foreach (['bad id', str_repeat('a', 65), ''] as $id) {
      $state = (new FormState())->setValues(['producer_id' => $id, 'site_id' => 'site-a.test']);
      $this->form->validateForm($form, $state);
      $this->assertArrayHasKey('producer_id', $state->getErrors(), $id);
    }
    $state = (new FormState())->setValues(['producer_id' => 'chat-node', 'site_id' => "site\n"]);
    $this->form->validateForm($form, $state);
    $this->assertArrayHasKey('site_id', $state->getErrors());
  }

  public function testAdminRouteIsPermissionGatedAndWiredToTheForm(): void {
    $module = dirname(__DIR__, 3);
    $routes = Yaml::parseFile($module . '/xinshi_ai_usage.routing.yml');
    $admin = $routes['xinshi_ai_usage.settings'];
    $this->assertSame('/admin/config/xinshi/ai/usage', $admin['path']);
    $this->assertStringStartsWith('\\' . ProducerSettingsForm::class, $admin['defaults']['_form']);
    $permissions = Yaml::parseFile($module . '/xinshi_ai_usage.permissions.yml');
    $permission = $admin['requirements']['_permission'];
    $this->assertTrue($permissions[$permission]['restrict access']);
    $route = new Route($admin['path'], [], $admin['requirements']);
    foreach ([TRUE, FALSE] as $granted) {
      $account = $this->createMock(AccountInterface::class);
      $account->method('hasPermission')->willReturnCallback(fn($name) => $granted && $name === $permission);
      $this->assertSame($granted, (new PermissionAccessCheck())->access($route, $account)->isAllowed());
    }
    $services = Yaml::parseFile($module . '/xinshi_ai_usage.services.yml')['services'];
    $this->assertSame(ProducerVault::class, $services['xinshi_ai_usage.producer_vault']['class']);
    $this->assertContains('@xinshi_ai_usage.producer_vault', $services['xinshi_ai_usage.producer_identity']['arguments']);
    $this->assertSame('xinshi_ai_usage.settings', Yaml::parseFile($module . '/xinshi_ai_usage.info.yml')['configure']);
    $this->assertSame('xinshi_ai.settings',
      Yaml::parseFile($module . '/xinshi_ai_usage.links.task.yml')['xinshi_ai_usage.settings.tab']['base_route']);
  }

  private static int $nonceCounter = 0;

  private function signed(string $body, string $producer, string $secret): Request {
    $timestamp = (string) $this->now;
    $nonce = 'nonce-' . str_pad((string) ++self::$nonceCounter, 16, '0', STR_PAD_LEFT);
    return Request::create(self::PATH, 'POST', [], [], [], [
      'HTTP_X_XINSHI_PRODUCER' => $producer,
      'HTTP_X_XINSHI_TIMESTAMP' => $timestamp,
      'HTTP_X_XINSHI_NONCE' => $nonce,
      'HTTP_X_XINSHI_SIGNATURE' => ProducerIdentity::sign($secret, $producer, $timestamp, $nonce, 'POST', self::PATH, $body),
      'CONTENT_TYPE' => 'application/json',
    ], $body);
  }

  private function authStatus(string $body, string $producer, string $secret): int {
    try {
      $this->identity->authenticate($this->signed($body, $producer, $secret));
      return 200;
    }
    catch (ServiceAuthException) {
      return 401;
    }
  }

}
