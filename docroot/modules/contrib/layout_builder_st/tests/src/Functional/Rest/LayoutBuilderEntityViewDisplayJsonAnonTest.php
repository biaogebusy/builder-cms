<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder_st\Functional\Rest;

use Drupal\Tests\rest\Functional\AnonResourceTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test JSON anonymous resource.
 *
 * @group layout_builder_st
 * @group rest
 */
#[Group('layout_builder_st')]
#[Group('rest')]
#[RunTestsInSeparateProcesses]
class LayoutBuilderEntityViewDisplayJsonAnonTest extends LayoutBuilderEntityViewDisplayResourceTestBase {

  use AnonResourceTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $format = 'json';

  /**
   * {@inheritdoc}
   */
  protected static $mimeType = 'application/json';

}
