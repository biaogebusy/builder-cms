<?php

namespace Drupal\Tests\shs\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Base test class for SHS browser tests.
 *
 * @group shs
 */
abstract class ShsTestBase extends BrowserTestBase {

  use ShsTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'shs'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The field to add to the content type for the taxonomy terms.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->fieldName = mb_strtolower($this->randomMachineName());
    $this->prepareSetup($this->fieldName);

    $permissions = ['create article content', 'administer taxonomy'];
    $this->drupalLogin($this->drupalCreateUser($permissions));
  }

}
