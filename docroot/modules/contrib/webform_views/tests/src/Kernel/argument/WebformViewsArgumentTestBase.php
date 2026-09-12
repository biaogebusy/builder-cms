<?php

namespace Drupal\Tests\webform_views\Kernel\argument;

use Drupal\Tests\webform_views\Kernel\WebformViewsTestBase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Reasonable starting point for testing webform views argument handlers.
 */
abstract class WebformViewsArgumentTestBase extends WebformViewsTestBase {

  /**
   * Test argument handler.
   *
   * @param string $argument
   *   Argument to supply to the view when executing it.
   * @param array $expected
   *   Expected output from $this->renderView() for the specified above
   *   argument.
   */
  #[DataProvider('providerArgument')]
  public function testArgument($argument, $expected): void {
    $this->webform = $this->createWebform(static::$webform_elements);
    $this->createWebformSubmissions(static::$webform_submissions_data, $this->webform);

    $this->view = $this->initView($this->webform, static::$view_handlers);

    $rendered_cells = $this->renderView($this->view, [$argument]);

    $this->assertSame($expected, $rendered_cells, 'Argument works for ' . $argument);
  }

  /**
   * Data provider for the ::testArgument() method.
   *
   * You might want to override this method with more specific cases in a child
   * class.
   */
  public static function providerArgument(): array {
    $tests = [];

    foreach (static::$webform_submissions_data as $submission) {
      $element = array_keys($submission);
      $element = reset($element);

      $tests[] = [
        $submission[$element],
        [$submission],
      ];
    }

    return $tests;
  }

}
