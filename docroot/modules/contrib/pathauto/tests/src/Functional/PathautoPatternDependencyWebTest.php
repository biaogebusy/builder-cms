<?php

namespace Drupal\Tests\pathauto\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the UI for config dependency handling of pathauto patterns.
 *
 * @group pathauto
 * @see https://www.drupal.org/project/pathauto/issues/3072876
 */
#[Group('pathauto')]
#[RunTestsInSeparateProcesses]
class PathautoPatternDependencyWebTest extends BrowserTestBase {

  use PathautoTestHelperTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'pathauto'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Basic page',
    ]);

    $this->drupalLogin($this->drupalCreateUser([
      'administer pathauto',
      'administer content types',
    ]));
  }

  /**
   * Tests the delete form shows the pattern as affected config.
   */
  public function testDeleteBundleShowsPatternAsAffected(): void {
    $pattern = $this->createPattern('node', '/content/[node:title]');
    $pattern->addSelectionCondition([
      'id' => 'entity_bundle:node',
      'bundles' => [
        'article' => 'article',
      ],
      'negate' => FALSE,
      'context_mapping' => ['node' => 'node'],
    ]);
    $pattern->save();

    // Visit the article delete form.
    $this->drupalGet('admin/structure/types/manage/article/delete');
    $this->assertSession()->statusCodeEquals(200);

    // The pattern should be listed under "Configuration updates".
    $this->assertSession()->elementTextContains(
      'css',
      '#edit-entity-updates',
      $pattern->label() ?: $pattern->id()
    );
  }

}
