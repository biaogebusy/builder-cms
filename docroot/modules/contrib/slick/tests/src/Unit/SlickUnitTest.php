<?php

namespace Drupal\Tests\slick\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\slick\Entity\Slick;
use Drupal\slick\SlickDefault;

/**
 * Testing \Drupal\slick\Entity\Slick.
 */
class SlickUnitTest extends UnitTestCase {

  /**
   * Tests for slick entity methods.
   */
  public function testSlickEntity() {
    $js_settings = SlickDefault::jsSettings();
    $this->assertArrayHasKey('lazyLoad', $js_settings);

    $dependent_options = Slick::getDependentOptions();
    $this->assertArrayHasKey('useCSS', $dependent_options);
  }

}
