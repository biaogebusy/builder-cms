<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Functional;

use Drupal\Core\Url;
use Drupal\easy_breadcrumb\EasyBreadcrumbConstants;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the CAPITALIZATOR_MODE configuration.
 */
#[Group('easy_breadcrumb')]
class EasyBreadcrumbCapitalizatorModeTest extends EasyBreadcrumbBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'easy_breadcrumb',
    'block',
    'node',
  ];

  /**
   * The testing node Url.
   */
  protected Url $url;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'page']);

    $node = $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'testing capitals and',
    ]);

    $this->url = $node->toUrl();
  }

  /**
   * Tests that the second crumb on the test pages contains specific text.
   */
  protected function secondSegmentTextEquals(string $text): void {
    $this->drupalGet($this->url);
    $this->easyBreadcrumbAssertSegmentTextEquals(2, $text);
  }

  /**
   * Tests the CAPITALIZATOR_MODE configuration.
   */
  public function testEasyBreadcrumbCapitalizatorMode() {
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::CAPITALIZATOR_MODE, 'none');
    $this->secondSegmentTextEquals('testing capitals and');

    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::CAPITALIZATOR_MODE, 'ucwords');
    $this->secondSegmentTextEquals('Testing Capitals and');

    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::CAPITALIZATOR_MODE, 'ucfirst');
    $this->secondSegmentTextEquals('Testing capitals and');

    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::CAPITALIZATOR_MODE, 'ucall');
    $this->secondSegmentTextEquals('TESTING CAPITALS AND');

    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::CAPITALIZATOR_MODE, 'ucforce');
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::CAPITALIZATOR_FORCED_WORDS, ['capitals']);
    $this->secondSegmentTextEquals('testing CAPITALS and');

    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::CAPITALIZATOR_FORCED_WORDS, ['CAPitals']);
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::CAPITALIZATOR_FORCED_WORDS_CASE_SENSITIVITY, FALSE);
    $this->secondSegmentTextEquals('testing CAPITALS and');

    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::CAPITALIZATOR_MODE, 'ucwords');
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::CAPITALIZATOR_IGNORED_WORDS, []);
    $this->secondSegmentTextEquals('Testing Capitals And');
  }

}
