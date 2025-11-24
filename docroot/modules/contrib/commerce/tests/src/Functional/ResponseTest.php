<?php

namespace Drupal\Tests\commerce\Functional;

/**
 * Tests to see if the "x-commerce-core" header is added.
 *
 * @group commerce
 */
class ResponseTest extends CommerceBrowserTestBase {

  /**
   * Test to see if the "x-commerce-core" header is added.
   */
  public function testGeneratorStringAndHeader() {
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);
    [$version] = explode('.', \Drupal::VERSION, 2);
    $this->assertSession()->responseContains('Drupal ' . $version . ' (https://www.drupal.org); Commerce 3');
    $this->assertEquals('3', $this->getSession()->getResponseHeader('X-Commerce-Core'));
    $this->assertSession()->responseHeaderContains('X-Commerce-Core', 3);
  }

}
