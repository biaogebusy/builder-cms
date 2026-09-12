<?php

declare(strict_types=1);

namespace Drupal\Tests\diff\Kernel;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\diff\Hook\DiffEntityHooks;
use Drupal\diff\Routing\DiffRouteProvider;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test cases for diff entity hooks.
 */
#[Group('diff')]
#[RunTestsInSeparateProcesses]
class DiffEntityHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'diff',
    'user',
    'config_test',
    'entity_test',
  ];

  /**
   * The subject under test.
   *
   * @var \Drupal\diff\Hook\DiffEntityHooks
   */
  protected DiffEntityHooks $hooks;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->hooks = $this->container->get(DiffEntityHooks::class);
  }

  /**
   * Data provider for non-applicable entity types.
   *
   * @return array[]
   *   An array of non-applicable entity type ids.
   */
  public static function nonApplicableEntityTypeProvider(): array {
    return [
      'non-content-entity-type' => ['config_test'],
      'no-route-providers' => ['entity_test_external'],
      'inapplicable-route-providers' => ['entity_test'],
      // @todo Find revisionable entity without version-history link template.
    ];
  }

  /**
   * Test case for entity type alter where diff should not apply.
   */
  #[DataProvider('nonApplicableEntityTypeProvider')]
  public function testNonApplicableEntityTypeAlter(string $entity_type_id): void {
    $entity_type_manager = $this->container->get('entity_type.manager');
    \assert($entity_type_manager instanceof EntityTypeManagerInterface);
    $entity_type = $entity_type_manager->getDefinition($entity_type_id);
    $entity_types = [$entity_type_id => $entity_type];

    $this->hooks->entityTypeAlter($entity_types);

    $handlers = $entity_type->get('handlers') ?? [];
    $route_providers = $handlers['route_providers'] ?? [];
    static::assertArrayNotHasKey('html_diff', $route_providers);
    static::assertFalse($entity_type->hasLinkTemplate('revisions-diff'));
  }

  /**
   * Test case for entity type alter where diff should apply.
   */
  public function testApplicableEntityTypeAlter(): void {
    $entity_type_manager = $this->container->get('entity_type.manager');
    \assert($entity_type_manager instanceof EntityTypeManagerInterface);
    $entity_type = $entity_type_manager->getDefinition('entity_test_rev');
    $entity_types = ['entity_test_rev' => $entity_type];

    $this->hooks->entityTypeAlter($entity_types);

    // Ensure that it does add things when appropriate.
    $handlers = $entity_type->get('handlers');
    static::assertSame(DiffRouteProvider::class, $handlers['route_provider']['html_diff']);
    static::assertTrue($entity_type->hasLinkTemplate('revisions-diff'));
    static::assertSame(
      '/entity_test_rev/{entity_test_rev}/revisions/diff/{left_revision}/{right_revision}/{filter}',
      $entity_type->getLinkTemplate('revisions-diff'),
    );
  }

}
