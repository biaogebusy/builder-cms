<?php

namespace Drupal\Tests\devel_generate\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\devel_generate\Traits\DevelGenerateSetupTrait;

/**
 * Base class for devel_generate functional browser tests.
 *
 * DevelGenerateCommandsTest should not extend this class so that it can remain
 * independent and be used as a cut-and-paste example for other developers.
 */
abstract class DevelGenerateBrowserTestBase extends BrowserTestBase {

  use DevelGenerateSetupTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'content_translation',
    'devel',
    'devel_generate',
    'language',
    'menu_ui',
    'node',
    'comment',
    'taxonomy',
    'path',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Prepares the testing environment.
   */
  public function setUp() {
    parent::setUp();
    $this->setUpData();
  }

}
