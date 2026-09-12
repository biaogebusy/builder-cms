<?php

namespace Drupal\Tests\ctools_views\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Tests config schema validation for ctools_views additions to views_block.
 *
 * @group ctools_views
 */
class CToolsViewsSchemaValidationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'views', 'ctools_views'];

  /**
   * Tests that ctools_views keys on views_block are optional.
   */
  public function testCtoolsViewsKeysAreOptional(): void {
    $typed_config = $this->container->get('config.typed');
    $data = [
      'id' => 'views_block:ctools_views_test_view-block_basic',
      'label' => 'Blog listing',
      'label_display' => '0',
      'provider' => 'views',
    ];

    $violations = $typed_config
      ->createFromNameAndData('block.settings.views_block:ctools_views_test_view-block_basic', $data)
      ->validate();

    $this->assertNoViolations($violations);
  }

  /**
   * Tests that ctools_views keys are accepted when explicitly provided.
   */
  public function testCtoolsViewsKeysAreValidWhenPresent(): void {
    $typed_config = $this->container->get('config.typed');
    $data = [
      'id' => 'views_block:ctools_views_test_view-block_basic',
      'label' => 'Blog listing',
      'label_display' => '0',
      'provider' => 'views',
      'pager' => 'some',
      'fields' => [],
      'filter' => [],
      'exposed' => [],
      'sort' => [],
      'pager_offset' => 0,
    ];

    $violations = $typed_config
      ->createFromNameAndData('block.settings.views_block:ctools_views_test_view-block_basic', $data)
      ->validate();

    $this->assertNoViolations($violations);
  }

  /**
   * Asserts that validation returned no violations.
   */
  protected function assertNoViolations(ConstraintViolationListInterface $violations): void {
    $messages = [];
    foreach ($violations as $violation) {
      $messages[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
    }
    $this->assertCount(0, $violations, implode(PHP_EOL, $messages));
  }

}
