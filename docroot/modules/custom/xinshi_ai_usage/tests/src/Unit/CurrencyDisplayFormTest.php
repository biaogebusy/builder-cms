<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai_usage\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\DependencyInjection\ReverseContainer;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\MemoryStorage;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Lock\NullLockBackend;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\user\Access\PermissionAccessCheck;
use Drupal\xinshi_ai_usage\Form\CurrencyDisplayForm;
use Drupal\xinshi_ai_usage\Service\CurrencyDisplay;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Routing\Route;
use Symfony\Component\Yaml\Yaml;

/** Verifies saved preferences, upgrades, form cache recovery and actual route access policy. */
final class CurrencyDisplayFormTest extends TestCase {

  private ConfigFactory $factory;
  private CurrencyDisplay $display;
  private CurrencyDisplayForm $form;

  protected function setUp(): void {
    $dispatcher = new EventDispatcher();
    $typed = $this->createMock(TypedConfigManagerInterface::class);
    $this->factory = new ConfigFactory(new MemoryStorage(), $dispatcher, $typed);
    $dispatcher->addSubscriber($this->factory);
    $state = $this->createMock(StateInterface::class);
    $state->method('get')->willReturn([]);
    $this->display = new CurrencyDisplay($this->factory, $state,
      new Client(['handler' => new MockHandler([new Response(503)])]),
      $this->createMock(TimeInterface::class), new NullLockBackend(), new NullLogger());
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translateString')->willReturnCallback(
      fn(TranslatableMarkup $markup) => $markup->getUntranslatedString());
    $container = new ContainerBuilder();
    $container->set('config.factory', $this->factory);
    $container->set('config.typed', $typed);
    $container->set('xinshi_ai_usage.currency_display', $this->display);
    $container->set('cache_tags.invalidator', $this->createMock(CacheTagsInvalidatorInterface::class));
    $container->set('string_translation', $translation);
    $container->set('messenger', $this->createMock(MessengerInterface::class));
    $container->set(ReverseContainer::class, new ReverseContainer($container));
    \Drupal::setContainer($container);
    $this->form = CurrencyDisplayForm::create($container);
  }

  protected function tearDown(): void {
    \Drupal::unsetContainer();
  }

  public function testDefaultSaveReloadAndUpgradeKeepTheAdministratorsPreference(): void {
    $form = $this->form->buildForm([], new FormState());
    $this->assertSame('CNY', $form['currency']['#default_value']);
    $this->assertTrue($this->factory->get(CurrencyDisplay::CONFIG)->isNew());
    xinshi_ai_usage_update_10009();
    $this->assertSame('CNY', $this->factory->get(CurrencyDisplay::CONFIG)->get('currency'));
    $state = (new FormState())->setValues(['currency' => 'USD']);
    $this->form->submitForm($form, $state);
    $this->assertSame('USD', $this->display->snapshot()['currency']);
    xinshi_ai_usage_update_10009();
    $this->assertSame('USD', $this->factory->get(CurrencyDisplay::CONFIG)->get('currency'));
    $restored = unserialize(serialize($this->form));
    $this->assertSame('USD', $restored->buildForm([], new FormState())['currency']['#default_value']);
  }

  public function testRefreshDoesNotSaveAnUnsubmittedCurrency(): void {
    $form = $this->form->buildForm([], new FormState());
    $state = (new FormState())->setValues(['currency' => 'USD']);
    $this->form->refreshRates($form, $state);
    $this->assertTrue($state->isRebuilding());
    $this->assertSame('CNY', $this->display->currency());
    $this->assertTrue($this->factory->get(CurrencyDisplay::CONFIG)->isNew());
  }

  public function testPricingPermissionControlsBothSettingsAndRefresh(): void {
    $module = dirname(__DIR__, 3);
    $definition = Yaml::parseFile($module . '/xinshi_ai_usage.routing.yml')['xinshi_ai_usage.currency_display'];
    $route = new Route($definition['path'], $definition['defaults'], $definition['requirements']);
    foreach ([FALSE, TRUE] as $allowed) {
      $account = $this->createMock(AccountInterface::class);
      $account->method('hasPermission')->with('administer xinshi ai pricing')->willReturn($allowed);
      $this->assertSame($allowed, (new PermissionAccessCheck())->access($route, $account)->isAllowed());
    }
  }

}
