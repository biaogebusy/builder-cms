<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\MemoryStorage;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Element\Number;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\Access\LoginStatusCheck;
use Drupal\user\Access\PermissionAccessCheck;
use Drupal\xinshi_ai\Controller\HarnessSettingsController;
use Drupal\xinshi_ai\Form\SettingsForm;
use Drupal\xinshi_ai\Service\HarnessSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Routing\Route;
use Symfony\Component\Yaml\Yaml;

/** Verifies the real form, configuration storage and authenticated API contract. */
final class HarnessSettingsTest extends TestCase {

  private MemoryStorage $storage;
  private ConfigFactory $factory;
  private SettingsForm $form;

  protected function setUp(): void {
    parent::setUp();
    $this->storage = new MemoryStorage();
    $this->storage->write('xinshi_ai.settings', [
      'gateway' => ['api_key' => 'secret-key', 'base_url' => 'https://example.test', 'request_timeout' => 180],
      'observability' => ['ingest_token_env' => 'SECRET_TOKEN_ENV'],
    ]);
    $typed = $this->createMock(TypedConfigManagerInterface::class);
    $dispatcher = new EventDispatcher();
    $this->factory = new ConfigFactory($this->storage, $dispatcher, $typed);
    $dispatcher->addSubscriber($this->factory);
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translateString')->willReturnCallback(
      fn(TranslatableMarkup $markup) => $markup->getUntranslatedString());
    $container = new ContainerBuilder();
    $container->set('config.factory', $this->factory);
    $container->set('string_translation', $translation);
    $container->set('messenger', $this->createMock(MessengerInterface::class));
    $container->set('cache_tags.invalidator', $this->createMock(CacheTagsInvalidatorInterface::class));
    \Drupal::setContainer($container);
    $this->form = new SettingsForm($this->factory, $typed);
  }

  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
  }

  public function testExistingSiteAndNewInstallHaveTheSameDefaults(): void {
    $response = (new HarnessSettingsController())->read();
    $this->assertSame(['version' => 1, ...HarnessSettings::DEFAULTS], json_decode($response->getContent(), TRUE));
    $installed = Yaml::parseFile(dirname(__DIR__, 3) . '/config/install/xinshi_ai.settings.yml');
    $this->assertSame(HarnessSettings::DEFAULTS, $installed['harness']);
    // Merely reading defaults does not write the site's configuration.
    $this->assertArrayNotHasKey('harness', $this->storage->read('xinshi_ai.settings'));
    $form = $this->form->buildForm([], new FormState());
    $this->assertFalse($form['harness']['tools']['pages_enabled']['#default_value']);
    $this->assertSame(40, $form['harness']['task_limits']['max_model_calls']['#default_value']);
    $this->assertSame(200000, $form['harness']['task_limits']['max_tokens']['#default_value']);
  }

  public function testApiIsAnUncachedAllowlistAndDoesNotExposeSecrets(): void {
    $this->factory->getEditable('xinshi_ai.settings')->set('harness', [
      'tools' => ['pages_enabled' => TRUE, 'secret' => 'hidden'],
      'task_limits' => ['max_model_calls' => 9, 'max_tokens' => 1200],
      'unknown' => 'must-not-expose',
    ])->save();
    $response = (new HarnessSettingsController())->read();
    $this->assertSame([
      'version' => 1, 'tools' => ['pages_enabled' => TRUE],
      'task_limits' => ['max_model_calls' => 9, 'max_tokens' => 1200],
    ], json_decode($response->getContent(), TRUE));
    $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
    $this->assertTrue($response->headers->hasCacheControlDirective('private'));
    $this->assertStringNotContainsString('secret', $response->getContent());
  }

  public function testMalformedConfigurationCannotEnableToolsOrSupplyInvalidLimits(): void {
    foreach ([NULL, FALSE, 'invalid', [], [
      'tools' => ['pages_enabled' => 'true'],
      'task_limits' => ['max_model_calls' => 0, 'max_tokens' => HarnessSettings::MAX_LIMIT + 1],
    ], ['tools' => 'invalid', 'task_limits' => ['max_model_calls' => 1.5, 'max_tokens' => '1000']]] as $value) {
      $this->assertSame(HarnessSettings::DEFAULTS, HarnessSettings::normalize($value));
    }
  }

  public function testFormSavesTypedSettingsAndPreservesUnrelatedConfiguration(): void {
    $state = (new FormState())->setValues([
      'base_url' => 'https://example.test', 'api_key' => 'secret-key', 'request_timeout' => '180',
      'default_n' => '4', 'worker' => 'daemon', 'ttl' => '300',
      'heartbeat_seconds' => '25', 'max_lifetime_seconds' => '1200',
      'harness' => [
        'tools' => ['pages_enabled' => '1'],
        'task_limits' => ['max_model_calls' => '7', 'max_tokens' => '1500'],
      ],
    ]);
    $form = $this->form->buildForm([], $state);
    $this->assertTrue($form['harness']['#tree']);
    $this->form->submitForm($form, $state);
    $stored = $this->storage->read('xinshi_ai.settings');
    $this->assertSame([
      'tools' => ['pages_enabled' => TRUE],
      'task_limits' => ['max_model_calls' => 7, 'max_tokens' => 1500],
    ], $stored['harness']);
    $this->assertSame('secret-key', $stored['gateway']['api_key']);
    $this->assertSame(['ingest_token_env' => 'SECRET_TOKEN_ENV'], $stored['observability']);
    $this->assertSame(7, json_decode((new HarnessSettingsController())->read()->getContent(), TRUE)['task_limits']['max_model_calls']);

    $state->setValue(['harness', 'tools', 'pages_enabled'], 0);
    $this->form->submitForm($form, $state);
    $this->assertFalse(json_decode((new HarnessSettingsController())->read()->getContent(), TRUE)['tools']['pages_enabled']);
  }

  public static function limits(): array {
    return [
      [0, FALSE], [-1, FALSE], ['1.5', FALSE], ['invalid', FALSE],
      [HarnessSettings::MAX_LIMIT + 1, FALSE],
      [1, TRUE], ['200000', TRUE], [(string) HarnessSettings::MAX_LIMIT, TRUE],
    ];
  }

  #[DataProvider('limits')]
  public function testLimitValidationUsesDrupalNumberConstraints(int|string $value, bool $valid): void {
    $form = $this->form->buildForm([], new FormState());
    foreach (['max_model_calls', 'max_tokens'] as $key) {
      $state = new FormState();
      $element = $form['harness']['task_limits'][$key];
      $element['#value'] = $value;
      $element['#parents'] = ['harness', 'task_limits', $key];
      Number::validateNumber($element, $state, $form);
      $this->assertSame($valid, $state->getErrors() === []);
    }
  }

  public function testReadRequiresLoginAndSavingRemainsAdministratorOnly(): void {
    $routes = Yaml::parseFile(dirname(__DIR__, 3) . '/xinshi_ai.routing.yml');
    $read = $routes['xinshi_ai.harness.settings'];
    $this->assertSame(['GET'], $read['methods']);
    $this->assertSame(['oauth2', 'cookie'], $read['options']['_auth']);
    $this->assertTrue($read['options']['no_cache']);
    $readRoute = new Route($read['path'], [], $read['requirements']);
    $write = $routes['xinshi_ai.settings'];
    $writeRoute = new Route($write['path'], [], $write['requirements']);
    foreach ([[FALSE, FALSE], [TRUE, FALSE], [TRUE, TRUE]] as [$loggedIn, $admin]) {
      $account = $this->createMock(AccountInterface::class);
      $account->method('isAuthenticated')->willReturn($loggedIn);
      $account->method('hasPermission')->willReturnCallback(
        fn($permission) => $admin && $permission === 'administer xinshi_ai');
      $this->assertSame($loggedIn, (new LoginStatusCheck())->access($account, $readRoute)->isAllowed());
      $this->assertSame($admin, (new PermissionAccessCheck())->access($writeRoute, $account)->isAllowed());
    }
  }

}
