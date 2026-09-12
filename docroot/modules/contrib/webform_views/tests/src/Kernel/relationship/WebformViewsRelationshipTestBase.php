<?php

namespace Drupal\Tests\webform_views\Kernel\relationship;

use Drupal\Tests\webform_views\Kernel\WebformViewsTestBase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Reasonable starting point for testing webform views relationships.
 */
abstract class WebformViewsRelationshipTestBase extends WebformViewsTestBase {

  /**
   * Entity type onto which relationship is being tested
   *
   * @var string
   */
  protected $target_entity_type;

  /**
   * Test relationship.
   *
   * @param array $expected
   *   Expected output from $this->renderView().
   */
  #[DataProvider('providerRelationship')]
  public function testRelationship(array $expected): void {
    $this->webform = $this->createWebform(static::$webform_elements);
    $this->createWebformSubmissions(static::$webform_submissions_data, $this->webform);

    $this->view = $this->initView($this->webform, static::$view_handlers);

    $rendered_cells = $this->renderView($this->view);

    $this->assertSame($expected, $rendered_cells, 'Relationship works.');
  }

  /**
   * Data provider for the ::testRelationship() method.
   *
   * You might want to override this method with more specific cases in a child
   * class.
   */
  public static function providerRelationship(): array {
    $tests = [];

    $expected = [];
    foreach (static::$webform_submissions_data as $webform_submission) {
      $target_id = reset($webform_submission);
      $expected[] = ['entity_id' => (string) $target_id];
    }
    $tests[] = [
      $expected,
    ];

    return $tests;
  }

  /**
   * Test the reverse relationship.
   *
   * @param array $expected
   *   Expected output from $this->renderView().
   */
  #[DataProvider('providerReverseRelationship')]
  public function testReverseRelationship(array $expected): void {
    $this->webform = $this->createWebform(static::$webform_elements);
    $this->createWebformSubmissions(static::$webform_submissions_data, $this->webform);

    $this->view = $this->initView($this->webform, [], 'webform_views_reverse_entity_reference_test');

    $rendered_cells = $this->renderView($this->view, [], []);

    $this->assertSame($expected, $rendered_cells, 'Reverse relationship works.');
  }

  /**
   * Data provider for the ::testReverseRelationship() method.
   *
   * You might want to override this method with more specific cases in a child
   * class.
   */
  public static function providerReverseRelationship(): array {
    $tests = [];

    $expected = [];
    foreach (static::$webform_submissions_data as $webform_submission) {
      $target_id = reset($webform_submission);
      $expected[] = [
        'entity_id' => (string) $target_id,
        'sid' => '1',
      ];
    }
    $tests[] = [
      $expected,
    ];

    return $tests;
  }

}
