<?php

namespace Drupal\Tests\webform\Kernel\Breadcrumb;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\Container;

/**
 * Test webform breadcrumb builder.
 *
 * @see: \Drupal\Tests\forum\Unit\Breadcrumb\ForumBreadcrumbBuilderBaseTest
 * @see: \Drupal\Tests\forum\Unit\Breadcrumb\ForumNodeBreadcrumbBuilderTest
 *
 * @coversDefaultClass \Drupal\webform\Breadcrumb\WebformBreadcrumbBuilder
 *
 * @group webform
 */
class WebformBreadcrumbBuilderTest extends UnitTestCase {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The webform request handler.
   *
   * @var \Drupal\webform\WebformRequestInterface
   */
  protected $requestHandler;

  /**
   * The translation manager.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $translationManager;

  /**
   * The webform breadcrumb builder.
   *
   * @var \Drupal\webform\Breadcrumb\WebformBreadcrumbBuilder
   */
  protected $breadcrumbBuilder;

  /**
   * Node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * Node with access.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $nodeAccess;

  /**
   * Webform.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\webform\WebformInterface
   */
  protected WebformInterface|MockObject $webform;

  /**
   * Webform with access.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\webform\WebformInterface
   */
  protected WebformInterface|MockObject $webformAccess;

  /**
   * Webform with access and is template.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\webform\WebformInterface
   */
  protected WebformInterface|MockObject $webformTemplate;

  /**
   * Webform submission.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\webform\WebformSubmissionInterface
   */
  protected WebformSubmissionInterface|MockObject $webformSubmission;

  /**
   * Webform submission with access.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\webform\WebformSubmissionInterface
   */
  protected WebformSubmissionInterface|MockObject $webformSubmissionAccess;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setUpMockEntities();

    // Make some test doubles.
    $this->moduleHandler = $this->createMock('Drupal\Core\Extension\ModuleHandlerInterface');
    $this->configFactory = $this->getConfigFactoryStub([
      'webform.settings' => ['ui' => ['toolbar_item' => FALSE]],
    ]);
    $this->requestHandler = $this->createMock('Drupal\webform\WebformRequestInterface');
    $this->translationManager = $this->createMock('Drupal\Core\StringTranslation\TranslationInterface');

    // Make an object to test.
    $this->breadcrumbBuilder = $this->getMockBuilder('Drupal\webform\Breadcrumb\WebformBreadcrumbBuilder')
      ->setConstructorArgs([$this->moduleHandler, $this->requestHandler, $this->translationManager, $this->configFactory])
      ->onlyMethods([])
      ->getMock();

    // Enable the webform_templates.module, so that we can testing breadcrumb
    // typing for templates.
    $this->moduleHandler->expects($this->any())
      ->method('moduleExists')
      ->with('webform_templates')
      ->willReturn(TRUE);

    // Add a translation manager for t().
    $translation_manager = $this->getStringTranslationStub();
    $property = new \ReflectionProperty('Drupal\webform\Breadcrumb\WebformBreadcrumbBuilder', 'stringTranslation');
    $property->setAccessible(TRUE);
    $property->setValue($this->breadcrumbBuilder, $translation_manager);

    // Setup mock cache context container.
    // @see \Drupal\Core\Breadcrumb\Breadcrumb
    // @see \Drupal\Core\Cache\RefinableCacheableDependencyTrait
    $cache_contexts_manager = $this->createMock('Drupal\Core\Cache\Context\CacheContextsManager');
    $cache_contexts_manager->method('assertValidTokens')->willReturn(TRUE);
    $container = new Container();
    $container->set('cache_contexts_manager', $cache_contexts_manager);
    \Drupal::setContainer($container);
  }

  /**
   * Tests WebformBreadcrumbBuilder::applies().
   *
   * @param bool $expected
   *   WebformBreadcrumbBuilder::applies() expected result.
   * @param string|null $route_name
   *   (optional) A route name.
   * @param array $parameter_map
   *   (optional) An array of parameter names and values.
   *
   * @dataProvider providerTestApplies
   * @covers ::applies
   */
  public function testApplies(bool $expected, ?string $route_name = NULL, array $parameter_map = []): void {
    foreach ($parameter_map as &$parameter_map_item) {
      foreach ($parameter_map_item as $index => $parameter_name) {
        if (is_callable($parameter_name)) {
          $parameter_map_item[$index] = $parameter_name($this);
        }
      }
    }
    unset($parameter_map_item);
    $route_match = $this->getMockRouteMatch($route_name, $parameter_map);
    $this->assertEquals($expected, $this->breadcrumbBuilder->applies($route_match));
  }

  /**
   * Provides test data for testApplies().
   *
   * @return array
   *   Array of datasets for testApplies().
   */
  public static function providerTestApplies(): array {
    $tests = [
      [FALSE],
      [FALSE, 'not'],
      [FALSE, 'webform'],
      [FALSE, 'entity.webform'],
      [TRUE, 'entity.webform.handler.'],
      [TRUE, 'entity.webform_ui.element'],
      [TRUE, 'entity.webform.user.submissions'],
      // Source entity.
      [TRUE, 'entity.{source_entity}.webform'],
      [TRUE, 'entity.{source_entity}.webform_submission'],
      [TRUE, 'entity.node.webform'],
      [TRUE, 'entity.node.webform_submission'],
      [TRUE, 'entity.node.webform.user.submissions'],
      // Submissions.
      [FALSE, 'entity.webform.user.submission'],
      [TRUE, 'entity.webform.user.submission', [['webform_submission', static fn (self $testcase) => $testcase->mockWebformSubmissionAccess()]]],
      [TRUE, 'webform', [['webform_submission', static fn (self $testcase) => $testcase->mockWebformSubmissionAccess()]]],
      // Translations.
      [FALSE, 'entity.webform.config_translation_overview'],
      [TRUE, 'entity.webform.config_translation_overview', [['webform', static fn (self $testcase) => $testcase->mockWebformAccess()]]],
    ];
    return $tests;
  }

  /**
   * Tests WebformBreadcrumbBuilder::type.
   *
   * @param string|null $expected
   *   WebformBreadcrumbBuilder::type set via
   *   WebformBreadcrumbBuilder::applies().
   * @param string|null $route_name
   *   (optional) A route name.
   * @param array $parameter_map
   *   (optional) An array of parameter names and values.
   *
   * @dataProvider providerTestType
   * @covers ::applies
   */
  public function testType(?string $expected, ?string $route_name = NULL, array $parameter_map = []): void {
    foreach ($parameter_map as &$parameter_map_item) {
      foreach ($parameter_map_item as $index => $parameter_name) {
        if (is_callable($parameter_name)) {
          $parameter_map_item[$index] = $parameter_name($this);
        }
      }
    }
    unset($parameter_map_item);
    $route_match = $this->getMockRouteMatch($route_name, $parameter_map);
    $this->breadcrumbBuilder->applies($route_match);
    $this->assertEquals($expected, $this->breadcrumbBuilder->getType());
  }

  /**
   * Provides test data for testType().
   *
   * @return array
   *   Array of datasets for testType().
   */
  public static function providerTestType(): array {
    $tests = [
      [NULL],
      // Source entity.
      ['webform_source_entity', 'entity.{source_entity}.webform'],
      ['webform_source_entity', 'entity.{source_entity}.webform_submission'],
      ['webform_source_entity', 'entity.node.webform'],
      ['webform_source_entity', 'entity.node.webform_submission'],
      // Element.
      ['webform_element', 'entity.webform_ui.element'],
      // Handler.
      ['webform_handler', 'entity.webform.handler.'],
      // User submissions.
      ['webform_user_submissions', 'entity.webform.user.submissions'],
      ['webform_source_entity', 'entity.{source_entity}.webform.user.submissions'],
      ['webform_source_entity', 'entity.node.webform.user.submissions'],
      // User submission.
      ['webform_user_submission', 'entity.webform.user.submission', [['webform_submission', static fn (self $testcase) => $testcase->mockWebformSubmission()]]],
      // Submission.
      [NULL, 'entity.webform_submission.canonical', [['webform_submission', static fn (self $testcase) => $testcase->mockWebformSubmission()]]],
      ['webform_submission', 'entity.webform_submission.canonical', [['webform_submission', static fn (self $testcase) => $testcase->mockWebformSubmissionAccess()]]],
      // Webform.
      [NULL, 'entity.webform.canonical', [['webform', static fn (self $testcase) => $testcase->mockWebform()]]],
      ['webform', 'entity.webform.canonical', [['webform', static fn (self $testcase) => $testcase->mockWebformAccess()]]],
      // Webform template.
      ['webform_template', 'entity.webform.canonical', [['webform', static fn (self $testcase) => $testcase->mockWebformTemplate()]]],
    ];
    return $tests;
  }

  /**
   * Test build source entity breadcrumbs.
   */
  public function testBuildSourceEntity(): void {
    $this->setSourceEntity($this->nodeAccess);
    $route_match = $this->getMockRouteMatch('entity.node.webform', [
      ['webform', $this->webformAccess],
      ['node', $this->nodeAccess],
    ]);
    $links = [
      Link::createFromRoute('Home', '<front>'),
      $this->node->toLink(),
    ];
    $this->assertLinks($route_match, $links);
  }

  /**
   * Test build source entity submissions breadcrumbs.
   */
  public function testBuildSourceEntitySubmissions(): void {
    $this->setSourceEntity($this->nodeAccess);
    $route_match = $this->getMockRouteMatch('entity.node.webform.user.submission', [
      ['webform_submission', $this->webformSubmissionAccess],
      ['webform', $this->webform],
      ['node', $this->node],
    ]);
    $links = [
      Link::createFromRoute('Home', '<front>'),
      $this->node->toLink(),
      Link::createFromRoute('Submissions', 'entity.node.webform.user.submissions', ['node' => 1]),
    ];
    $this->assertLinks($route_match, $links);
  }

  /**
   * Test build source entity submissions breadcrumbs.
   */
  public function testBuildSourceEntityResults(): void {
    $this->setSourceEntity($this->nodeAccess);
    $route_match = $this->getMockRouteMatch('entity.node.webform_submission.canonical', [
      ['webform_submission', $this->webformSubmissionAccess],
      ['webform', $this->webform],
      ['node', $this->node],
    ]);
    $links = [
      Link::createFromRoute('Home', '<front>'),
      $this->node->toLink(),
      Link::createFromRoute('Results', 'entity.node.webform.results_submissions', ['node' => 1]),
    ];
    $this->assertLinks($route_match, $links);
  }

  /**
   * Test build source entity submissions breadcrumbs.
   */
  public function testBuildSourceEntityUserResults(): void {
    $this->setSourceEntity($this->node);
    $webform_submission_access = $this->createMock('Drupal\webform\WebformSubmissionInterface');
    $webform_submission_access->expects($this->any())
      ->method('access')
      ->willReturnCallback(function ($operation) {
        return ($operation === 'view_own');
      });
    $route_match = $this->getMockRouteMatch('entity.node.webform_submission.canonical', [
      ['webform_submission', $webform_submission_access],
      ['webform', $this->webform],
      ['node', $this->node],
    ]);
    $links = [
      Link::createFromRoute('Home', '<front>'),
      $this->node->toLink(),
      Link::createFromRoute('Results', 'entity.node.webform.user.submissions', ['node' => 1]),
    ];
    $this->assertLinks($route_match, $links);
  }

  /**
   * Test build templates breadcrumbs.
   */
  public function testBuildTemplates(): void {
    $route_match = $this->getMockRouteMatch('entity.webform.canonical', [
      ['webform', $this->webformTemplate],
    ]);
    $links = [
      Link::createFromRoute('Home', '<front>'),
      Link::createFromRoute('Administration', 'system.admin'),
      Link::createFromRoute('Structure', 'system.admin_structure'),
      Link::createFromRoute('Webforms', 'entity.webform.collection'),
      Link::createFromRoute('Templates', 'entity.webform.templates'),
    ];
    $this->assertLinks($route_match, $links);
  }

  /**
   * Test build element breadcrumbs.
   */
  public function testBuildElements(): void {
    $route_match = $this->getMockRouteMatch('entity.webform_ui.element', [
      ['webform', $this->webform],
    ]);
    $links = [
      Link::createFromRoute('Home', '<front>'),
      Link::createFromRoute('Administration', 'system.admin'),
      Link::createFromRoute('Structure', 'system.admin_structure'),
      Link::createFromRoute('Webforms', 'entity.webform.collection'),
      Link::createFromRoute($this->webform->label(), 'entity.webform.canonical', ['webform' => $this->webform->id()]),
      Link::createFromRoute('Elements', 'entity.webform.edit_form', ['webform' => $this->webform->id()]),
    ];
    $this->assertLinks($route_match, $links);
  }

  /**
   * Test build handler breadcrumbs.
   */
  public function testBuildHandlers(): void {
    // Check source entity.
    $route_match = $this->getMockRouteMatch('entity.webform.handler.add_form', [
      ['webform', $this->webform],
    ]);
    $links = [
      Link::createFromRoute('Home', '<front>'),
      Link::createFromRoute('Administration', 'system.admin'),
      Link::createFromRoute('Structure', 'system.admin_structure'),
      Link::createFromRoute('Webforms', 'entity.webform.collection'),
      Link::createFromRoute($this->webform->label(), 'entity.webform.canonical', ['webform' => $this->webform->id()]),
      Link::createFromRoute('Emails / Handlers', 'entity.webform.handlers', ['webform' => $this->webform->id()]),
    ];
    $this->assertLinks($route_match, $links);
  }

  /**
   * Test build submissions breadcrumbs.
   */
  public function testBuildSubmissions(): void {
    $route_match = $this->getMockRouteMatch('entity.webform_submission.canonical', [
      ['webform_submission', $this->webformSubmissionAccess],
    ]);
    $links = [
      Link::createFromRoute('Home', '<front>'),
      Link::createFromRoute('Administration', 'system.admin'),
      Link::createFromRoute('Structure', 'system.admin_structure'),
      Link::createFromRoute('Webforms', 'entity.webform.collection'),
      Link::createFromRoute($this->webform->label(), 'entity.webform.canonical', ['webform' => $this->webform->id()]),
      Link::createFromRoute('Results', 'entity.webform.results_submissions', ['webform' => $this->webform->id()]),
    ];
    $this->assertLinks($route_match, $links);
  }

  /**
   * Test build user submissions breadcrumbs.
   */
  public function testBuildUserSubmissions(): void {
    // Check without view own access.
    $route_match = $this->getMockRouteMatch('entity.webform.user.submission', [
      ['webform_submission', $this->webformSubmission],
    ]);
    $links = [
      Link::createFromRoute($this->webform->label(), 'entity.webform.canonical', ['webform' => $this->webform->id()]),
    ];
    $this->assertLinks($route_match, $links);

    // Check with view own access.
    $route_match = $this->getMockRouteMatch('entity.webform.user.submission', [
      ['webform_submission', $this->webformSubmissionAccess],
    ]);
    $links = [
      Link::createFromRoute($this->webform->label(), 'entity.webform.canonical', ['webform' => $this->webform->id()]),
      Link::createFromRoute('Submissions', 'entity.webform.user.submissions', ['webform' => $this->webform->id()]),
    ];
    $this->assertLinks($route_match, $links);

  }

  /**
   * Test build user submission breadcrumbs.
   */
  public function testBuildUserSubmission(): void {
    $route_match = $this->getMockRouteMatch('entity.webform.user.submissions', [
      ['webform', $this->webform],
    ]);
    $links = [
      Link::createFromRoute($this->webform->label(), 'entity.webform.canonical', ['webform' => $this->webform->id()]),
    ];
    $this->assertLinks($route_match, $links);
  }

  /* ************************************************************************ */
  // Helper functions.
  /* ************************************************************************ */

  /**
   * Assert breadcrumb builder generates links for specified route match.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   A mocked route match.
   * @param array $links
   *   An array of breadcrumb links.
   */
  protected function assertLinks(RouteMatchInterface $route_match, array $links): void {
    $this->breadcrumbBuilder->applies($route_match);
    $breadcrumb = $this->breadcrumbBuilder->build($route_match);
    $this->assertEquals($links, $breadcrumb->getLinks());
  }

  /**
   * Set request handler's source entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An entity.
   */
  protected function setSourceEntity(EntityInterface $entity) {
    // Set the node as the request handler's source entity.
    $this->requestHandler->expects($this->any())
      ->method('getCurrentSourceEntity')
      ->willReturn($entity);
  }

  /**
   * Get mock route match.
   *
   * @param string|null $route_name
   *   (optional) A route name.
   * @param array $parameter_map
   *   (optional) An array of parameter names and values.
   *
   * @return \Drupal\Core\Routing\RouteMatchInterface
   *   A mocked route match.
   */
  protected function getMockRouteMatch($route_name = NULL, array $parameter_map = []) {
    $route_match = $this->createMock('Drupal\Core\Routing\RouteMatchInterface');
    $route_match->expects($this->any())
      ->method('getRouteName')
      ->willReturn($route_name);
    $route_match->expects($this->any())
      ->method('getParameter')
      ->willReturnMap($parameter_map);

    /** @var \Drupal\Core\Routing\RouteMatchInterface $route_match */
    return $route_match;
  }

  /**
   * Setup mock webform and webform submission entities.
   *
   * This is called before every test is setup and provider initialization.
   */
  protected function setUpMockEntities() {
    // Only initial mock entities once.
    if (isset($this->node)) {
      return;
    }

    /* node entities */

    $this->node = $this->createMock('Drupal\node\NodeInterface');
    $this->node->expects($this->any())
      ->method('label')
      ->willReturn('{node}');
    $this->node->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn('node');
    $this->node->expects($this->any())
      ->method('id')
      ->willReturn('1');
    $this->node->expects($this->any())
      ->method('toLink')
      ->willReturn(Link::createFromRoute('{node}', 'entity.node.canonical', ['node' => 1]));

    $this->nodeAccess = clone $this->node;
    $this->nodeAccess->expects($this->any())
      ->method('access')
      ->willReturn(TRUE);

    /* webform entities */

    $this->webform = $this->createMock('Drupal\webform\WebformInterface');
    $this->webform->expects($this->any())
      ->method('label')
      ->willReturn('{webform}');
    $this->webform->expects($this->any())
      ->method('id')
      ->willReturn(1);

    $this->webformAccess = clone $this->webform;
    $this->webformAccess->expects($this->any())
      ->method('access')
      ->willReturn(TRUE);

    $this->webformTemplate = clone $this->webformAccess;
    $this->webformTemplate->expects($this->any())
      ->method('isTemplate')
      ->willReturn(TRUE);

    /* webform submission entities */

    $this->webformSubmission = $this->createMock('Drupal\webform\WebformSubmissionInterface');
    $this->webformSubmission->expects($this->any())
      ->method('getWebform')
      ->willReturn($this->webform);
    $this->webformSubmission->expects($this->any())
      ->method('label')
      ->willReturn('{webform_submission}');
    $this->webformSubmission->expects($this->any())
      ->method('id')
      ->willReturn(1);

    $this->webformSubmissionAccess = clone $this->webformSubmission;
    $this->webformSubmissionAccess->expects($this->any())
      ->method('access')
      ->willReturn(TRUE);
  }

  /**
   * Returns a Webform mock.
   */
  protected function mockWebform(): WebformInterface|MockObject {
    $mock = $this->createMock(WebformInterface::class);
    $mock
      ->method('label')
      ->willReturn('{webform}');
    $mock
      ->method('id')
      ->willReturn(1);

    return $mock;
  }

  /**
   * Returns a Webform mock with access.
   */
  protected function mockWebformAccess(): WebformInterface|MockObject {
    $mock = clone $this->webform;
    $mock
      ->method('access')
      ->willReturn(TRUE);

    return $mock;
  }

  /**
   * Returns Webform mock with access and is template.
   */
  protected function mockWebformTemplate(): WebformInterface|MockObject {
    $mock = clone $this->webformAccess;
    $mock
      ->method('isTemplate')
      ->willReturn(TRUE);

    return $mock;
  }

  /**
   * Returns Webform submission mock.
   */
  protected function mockWebformSubmission(): WebformSubmissionInterface|MockObject {
    $mock = $this->createMock(WebformSubmissionInterface::class);
    $mock
      ->method('getWebform')
      ->willReturn($this->webform);
    $mock
      ->method('label')
      ->willReturn('{webform_submission}');
    $mock
      ->method('id')
      ->willReturn(1);

    return $mock;
  }

  /**
   * Returns Webform submission mock with access.
   */
  protected function mockWebformSubmissionAccess(): WebformSubmissionInterface|MockObject {
    $mock = clone $this->webformSubmission;
    $mock
      ->method('access')
      ->willReturn(TRUE);

    return $mock;
  }

}

if (!function_exists('base_path')) {

  /**
   * Mock base path function.
   *
   * @return string
   *   A base path.
   */
  function base_path() {
    return '/';
  }

}
