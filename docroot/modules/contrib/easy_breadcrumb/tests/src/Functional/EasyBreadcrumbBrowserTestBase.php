<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Functional;

use Drupal\easy_breadcrumb\EasyBreadcrumbConstants;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test base for the Easy Breadcrumb module.
 */
#[Group('easy_breadcrumb')]
abstract class EasyBreadcrumbBrowserTestBase extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $easyBreadcrumbConfig;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->easyBreadcrumbConfig = $this->config(EasyBreadcrumbConstants::MODULE_SETTINGS);
    $this->placeBlock('system_breadcrumb_block', ['id' => 'breadcrumb']);
  }

  /**
   * Asserts that there is no breadcrumb.
   */
  protected function easyBreadcrumbAssertBreadcrumbNotExists(): void {
    $this->assertSession()->elementNotExists(
      'css',
      '#block-breadcrumb',
    );
  }

  /**
   * Checks case-sensitive text in a breadcrumb segment.
   *
   * @param int $segmentNumber
   *   The crumb segment order number.
   * @param string $text
   *   The text that is being tested for.
   */
  protected function easyBreadcrumbAssertSegmentTextEquals(int $segmentNumber, string $text): void {
    $textBreadcrumb = $this->getSession()->getPage()->find(
      'css',
      '#block-breadcrumb li:nth-child(' . $segmentNumber . ')',
    )?->getText();
    $this->assertEquals($text, $textBreadcrumb);
  }

  /**
   * Asserts that a breadcrumb segment does not exist.
   *
   * @param int $segmentNumber
   *   The segment order number.
   */
  protected function easyBreadcrumbAssertSegmentNotExists(int $segmentNumber): void {
    $this->assertSession()->elementNotExists(
      'css',
      '#block-breadcrumb li:nth-child(' . $segmentNumber . ')',
    );
  }

  /**
   * Creates and logs in a user with the administrator role.
   */
  protected function easyBreadcrumbCreateAndLoginAdminUser() {
    $this->drupalLogin($this->createUser([], NULL, TRUE));
  }

  /**
   * Creates and logs in a default user.
   */
  protected function easyBreadcrumbCreateAndLoginUser() {
    $this->drupalLogin($this->createUser());
  }

  /**
   * Sets configuration for Easy Breadcrumb.
   *
   * @param string $name
   *   Configuration name.
   * @param mixed $value
   *   Configuration value.
   *
   * @return \Drupal\Core\Config\Config
   *   The configuration object with original configuration data.
   */
  protected function easyBreadcrumbSetConfig($name, $value) {
    return $this->easyBreadcrumbConfig->set($name, $value)->save();
  }

}
