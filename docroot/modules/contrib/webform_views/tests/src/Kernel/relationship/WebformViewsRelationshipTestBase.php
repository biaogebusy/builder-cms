<?php

namespace Drupal\Tests\webform_views\Kernel\relationship;

use Drupal\Tests\webform_views\Kernel\WebformViewsTestBase;

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
   *
   * @dataProvider providerRelationship()
   */
  public function testRelationship($expected) {
    $this->webform = $this->createWebform($this->webform_elements);
    $this->createWebformSubmissions($this->webform_submissions_data, $this->webform);

    $this->view = $this->initView($this->webform, $this->view_handlers);

    $rendered_cells = $this->renderView($this->view);

    $this->assertSame($expected, $rendered_cells, 'Relationship works.');
  }

  /**
   * Data provider for the ::testRelationship() method.
   *
   * You might want to override this method with more specific cases in a child
   * class.
   */
  public function providerRelationship() {
    $tests = [];

    $expected = [];
    foreach ($this->webform_submissions_data as $webform_submission) {
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
   *
   * @dataProvider providerReverseRelationship()
   */
  public function testReverseRelationship($expected) {
    $this->webform = $this->createWebform($this->webform_elements);
    $this->createWebformSubmissions($this->webform_submissions_data, $this->webform);

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
  public function providerReverseRelationship() {
    $tests = [];

    $expected = [];
    foreach ($this->webform_submissions_data as $webform_submission) {
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
