<?php

declare(strict_types=1);

namespace Drupal\Tests\content_lock\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\content_lock\Functional\ContentLockTestTrait;
use Drupal\Tests\content_lock\Tools\LogoutTrait;

/**
 * Base class for content lock tests.
 */
abstract class ContentLockJavascriptTestBase extends WebDriverTestBase {
  use LogoutTrait;
  use ContentLockTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'content_lock',
  ];

}
