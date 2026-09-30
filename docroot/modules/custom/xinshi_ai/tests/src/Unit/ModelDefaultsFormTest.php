<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Cache\NullBackend;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\MemoryStorage;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\user\Access\PermissionAccessCheck;
use Drupal\xinshi_ai\Controller\ModelManageController;
use Drupal\xinshi_ai\Controller\ModelRegistryController;
use Drupal\xinshi_ai\Form\ModelDefaultsForm;
use Drupal\xinshi_ai\Service\ModelRegistryService;
use Drupal\xinshi_ai\Service\ModelRegistryServiceInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Yaml\Yaml;

/** Exercise admin defaults with real storage and both model management views. */
final class ModelDefaultsFormTest extends TestCase {

  private ConfigFactory $factory;
  private ModelRegistryService $registry;
  private ModelDefaultsForm $form;

  protected function setUp(): void {
    $dispatcher = new EventDispatcher();
    $this->factory = new ConfigFactory(new MemoryStorage(), $dispatcher,
      $this->createMock(TypedConfigManagerInterface::class));
    $dispatcher->addSubscriber($this->factory);
    $this->registry = new ModelRegistryService($this->factory, new NullBackend('test'));
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translateString')->willReturnCallback(
      fn(TranslatableMarkup $markup) => $markup->getUntranslatedString());
    $container = new ContainerBuilder();
    $container->set('config.factory', $this->factory);
    $container->set('cache_tags.invalidator', $this->createMock(CacheTagsInvalidatorInterface::class));
    $container->set('string_translation', $translation);
    $container->set('messenger', $this->createMock(MessengerInterface::class));
    $container->set('xinshi_ai.model_registry', $this->registry);
    \Drupal::setContainer($container);
    $this->form = ModelDefaultsForm::create($container);
    $this->factory->getEditable('xinshi_ai.models')->setData([
      'platforms' => ['xinshi' => ['enabled' => TRUE], 'custom' => ['enabled' => TRUE]],
      'models' => [
        ['id' => 'chat-a', 'label' => 'Chat A', 'platform' => 'xinshi', 'capabilities' => ['chat']],
        ['id' => 'chat-b', 'platform' => 'xinshi', 'capabilities' => ['chat']],
        ['id' => 'images', 'platform' => 'xinshi', 'capabilities' => ['image', 'image-edit']],
        ['id' => 'custom-chat', 'platform' => 'custom', 'capabilities' => ['chat']],
        ['id' => 'disabled', 'platform' => 'xinshi', 'capabilities' => ['chat'], 'enabled' => FALSE],
      ],
      'defaults' => ['chat' => 'chat-a', 'critic' => 'chat-a', 'classifier' => 'chat-b', 'image' => 'images'],
    ])->save();
  }

  protected function tearDown(): void {
    (new FormState())->clearErrors();
    \Drupal::unsetContainer();
  }

  public function testShowsSavedSelectionsAndOnlyCompatibleEnabledChoicesWithoutWriting(): void {
    $before = $this->factory->get('xinshi_ai.models')->getRawData();
    $form = $this->form->buildForm([], new FormState());
    foreach (ModelRegistryServiceInterface::DEFAULT_MODES as $mode) {
      $this->assertSame($before['defaults'][$mode] ?? '', $form[$mode]['#default_value']);
      $this->assertNotEmpty((string) $form[$mode]['#title']);
      $this->assertSame('', $form[$mode]['#empty_value']);
    }
    $this->assertSame(['chat-a', 'chat-b'], array_keys($form['critic']['#options']));
    $this->assertSame(['chat-a', 'chat-b'], array_keys($form['classifier']['#options']));
    $this->assertSame(['chat-a', 'chat-b', 'custom-chat'], array_keys($form['chat']['#options']));
    $this->assertSame(['images'], array_keys($form['image']['#options']));
    $this->assertSame(['images'], array_keys($form['image-edit']['#options']));
    $this->assertSame($before, $this->factory->get('xinshi_ai.models')->getRawData());
  }

  public function testFormAndBuilderApiReadEachOthersChangesThroughTheSameRegistry(): void {
    $version = $this->registry->getVersion();
    $defaults = ['chat' => 'custom-chat', 'critic' => 'chat-b', 'classifier' => 'chat-a',
      'image' => 'images', 'image-edit' => 'images'];
    $this->save($defaults);
    $this->assertSame($defaults, $this->registry->getDefaults());
    $this->assertNotSame($version, $this->registry->getVersion());
    $manage = new ModelManageController($this->registry);
    $this->assertSame($defaults, json_decode($manage->list()->getContent(), TRUE)['defaults']);
    $public = (new ModelRegistryController($this->registry))->list(new Request());
    $this->assertSame($defaults, json_decode($public->getContent(), TRUE)['defaults']);
    $this->assertContains('config:xinshi_ai.models', $public->getCacheableMetadata()->getCacheTags());

    $manage->updateDefaults(Request::create('/', 'PATCH', [], [], [], [],
      '{"classifier":"chat-b","critic":null}'));
    $form = $this->form->buildForm([], new FormState());
    $this->assertSame('chat-b', $form['classifier']['#default_value']);
    $this->assertSame('', $form['critic']['#default_value']);
    $this->assertSame('custom-chat', $form['chat']['#default_value']);
  }

  public function testExplicitEmptySelectionsClearOnlyThoseRolesAndCanClearAll(): void {
    $this->save(['chat' => 'chat-a', 'critic' => '', 'classifier' => '', 'image' => 'images']);
    $this->assertSame(['chat' => 'chat-a', 'image' => 'images'], $this->registry->getDefaults());
    $this->save([]);
    $this->assertSame([], $this->registry->getDefaults());
    $this->assertNull($this->factory->get('xinshi_ai.models')->get('defaults'));
    $manage = (new ModelManageController($this->registry))->list();
    $this->assertEquals(new \stdClass(), json_decode($manage->getContent())->defaults);
  }

  public static function invalidSelections(): array {
    return [
      ['critic', 'custom-chat'], ['classifier', 'custom-chat'],
      ['critic', 'images'], ['classifier', 'images'],
      ['critic', 'disabled'], ['classifier', 'missing'],
      ['chat', 'images'], ['image', 'chat-a'], ['image-edit', 'chat-b'],
      ['classifier', ['chat-a']],
    ];
  }

  #[DataProvider('invalidSelections')]
  public function testFormAndApiRejectIncompatibleSelectionsWithoutWriting(string $mode, mixed $id): void {
    $before = $this->registry->getDefaults();
    $values = array_replace($before, [$mode => $id]);
    $state = (new FormState())->setValues($values);
    $form = $this->form->buildForm([], $state);
    $this->form->validateForm($form, $state);
    $this->assertArrayHasKey($mode, $state->getErrors());
    $this->assertSame($before, $this->registry->getDefaults());
    $response = (new ModelManageController($this->registry))->updateDefaults(
      Request::create('/', 'PATCH', [], [], [], [], json_encode($values)));
    $this->assertSame(422, $response->getStatusCode());
    $this->assertArrayHasKey($mode, json_decode($response->getContent(), TRUE)['errors']);
    $this->assertSame($before, $this->registry->getDefaults());
  }

  public function testInvalidSavedDefaultStaysVisibleUntilExplicitlyCleared(): void {
    $this->factory->getEditable('xinshi_ai.models')->set('defaults.classifier', 'missing')->save();
    $form = $this->form->buildForm([], new FormState());
    $this->assertSame('missing', $form['classifier']['#default_value']);
    $this->assertStringContainsString('不可用', (string) $form['classifier']['#options']['missing']);
    $state = (new FormState())->setValues($this->registry->getDefaults());
    $this->form->validateForm($form, $state);
    $this->assertArrayHasKey('classifier', $state->getErrors());
    $state->clearErrors();
    $this->save(array_replace($state->getValues(), ['classifier' => '']));
    $this->assertArrayNotHasKey('classifier', $this->registry->getDefaults());
    $this->assertSame('chat-a', $this->registry->getDefaults()['critic']);
  }

  public function testDisablingPlatformAfterDisplayRejectsSubmissionAndRemovesChoices(): void {
    $state = (new FormState())->setValues($this->registry->getDefaults());
    $form = $this->form->buildForm([], $state);
    $this->factory->getEditable('xinshi_ai.models')->set('platforms.xinshi.enabled', FALSE)->save();
    $this->form->validateForm($form, $state);
    $this->assertArrayHasKey('critic', $state->getErrors());
    $this->assertArrayHasKey('classifier', $state->getErrors());
    $form = $this->form->buildForm([], new FormState());
    // The current invalid value is retained as a warning, not offered as an eligible alternative.
    $this->assertSame(['chat-a'], array_keys($form['critic']['#options']));
    $this->assertSame(['chat-b'], array_keys($form['classifier']['#options']));
  }

  public function testAdminRouteIsDiscoverableAndRequiresModelManagementPermission(): void {
    $root = dirname(__DIR__, 3);
    $definition = Yaml::parseFile($root . '/xinshi_ai.routing.yml')['xinshi_ai.models.defaults'];
    $route = new Route($definition['path'], $definition['defaults'], $definition['requirements']);
    $this->assertSame('\Drupal\xinshi_ai\Form\ModelDefaultsForm', $route->getDefault('_form'));
    $this->assertTrue($definition['options']['_admin_route']);
    $tabs = Yaml::parseFile($root . '/xinshi_ai.links.task.yml');
    $this->assertSame('xinshi_ai.models.defaults', $tabs['xinshi_ai.models.defaults.tab']['route_name']);
    $this->assertSame('xinshi_ai.settings', $tabs['xinshi_ai.models.defaults.tab']['base_route']);
    foreach ([FALSE, TRUE] as $allowed) {
      $account = $this->createMock(AccountInterface::class);
      $account->method('hasPermission')->with('administer xinshi_ai')->willReturn($allowed);
      $this->assertSame($allowed, (new PermissionAccessCheck())->access($route, $account)->isAllowed());
    }
  }

  private function save(array $values): void {
    $state = (new FormState())->setValues(array_replace(
      array_fill_keys(ModelRegistryServiceInterface::DEFAULT_MODES, ''), $values));
    $form = $this->form->buildForm([], $state);
    $this->form->validateForm($form, $state);
    $this->assertSame([], $state->getErrors());
    $this->form->submitForm($form, $state);
    $this->assertSame('xinshi_ai.models.defaults', $state->getRedirect()->getRouteName());
  }

}
