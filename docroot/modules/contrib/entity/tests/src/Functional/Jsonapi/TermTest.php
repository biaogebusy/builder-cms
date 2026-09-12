<?php

namespace Drupal\Tests\entity\Functional\Jsonapi;

use Drupal\Tests\jsonapi\Functional\TermTest as CoreTermTest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Entity with JSON:API and entity_test.
 *
 * @group entity
 */
#[Group('entity')]
#[RunTestsInSeparateProcesses]
class TermTest extends CoreTermTest {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity'];

}
