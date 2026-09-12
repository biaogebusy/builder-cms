<?php

namespace Drupal\Tests\webform\Unit\Plugin\views\field;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\ResettableStackedRouteMatchInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\webform\Plugin\views\field\WebformSubmissionBulkForm;

/**
 * Tests webform submission bulk form actions.
 *
 * @see \Drupal\Tests\node\Unit\Plugin\views\field\NodeBulkFormTest
 * @see \Drupal\Tests\user\Unit\Plugin\views\field\UserBulkFormTest
 * @coversDefaultClass \Drupal\webform\Plugin\views\field\WebformSubmissionBulkForm
 * @group webform
 */
class WebformSubmissionBulkFormTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    // @todo Fix broken test. Skip the test until then.
    $this->markTestSkipped('This test is skipped until it is fixed ...');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    $container = new ContainerBuilder();
    \Drupal::setContainer($container);
  }

  /**
   * Tests the constructor assignment of actions.
   */
  public function testConstructor(): void {

    $actions = [];

    for ($i = 1; $i <= 2; $i++) {
      $action = $this->createMock('\Drupal\system\ActionConfigEntityInterface');
      $action->expects($this->any())
        ->method('getType')
        ->willReturn('webform_submission');
      $actions[$i] = $action;
    }

    $action = $this->createMock('\Drupal\system\ActionConfigEntityInterface');
    $action->expects($this->any())
      ->method('getType')
      ->willReturn('user');
    $actions[] = $action;

    $entity_storage = $this->createMock('Drupal\Core\Entity\EntityStorageInterface');
    $entity_storage->expects($this->any())
      ->method('loadMultiple')
      ->willReturn($actions);

    $entity_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_manager->expects($this->once())
      ->method('getStorage')
      ->with('action')
      ->willReturn($entity_storage);

    $entity_repository = $this->createMock(EntityRepositoryInterface::class);

    $route_match = $this->createMock(ResettableStackedRouteMatchInterface::class);

    $language_manager = $this->createMock('Drupal\Core\Language\LanguageManagerInterface');

    $messenger = $this->createMock('\Drupal\Core\Messenger\MessengerInterface');

    $views_data = $this->createMock('Drupal\views\ViewsData');
    $views_data->expects($this->any())
      ->method('get')
      ->with('webform_submission')
      ->willReturn(['table' => ['entity type' => 'webform_submission']]);
    $container = new ContainerBuilder();
    $container->set('views.views_data', $views_data);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $storage = $this->createMock('Drupal\views\ViewEntityInterface');
    $storage->expects($this->any())
      ->method('get')
      ->with('base_table')
      ->willReturn('webform_submission');

    $executable = $this->createMock('Drupal\views\ViewExecutable');
    $executable->storage = $storage;

    $display = $this->createMock('Drupal\views\Plugin\views\display\DisplayPluginBase');

    $definition['title'] = '';
    $options = [];

    $webform_submission_bulk_form = new WebformSubmissionBulkForm([], 'webform_submission_bulk_form', $definition, $entity_manager, $language_manager, $messenger, $entity_repository, $route_match);
    $webform_submission_bulk_form->init($executable, $display, $options);

    $this->assertEquals(array_slice($actions, 0, -1, TRUE), $webform_submission_bulk_form->actions);
  }

}
