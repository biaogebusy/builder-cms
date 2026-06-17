<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder_st\Functional\Rest;

use Drupal\Tests\rest\Functional\BasicAuthResourceTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test JSON basic auth resource.
 *
 * @group layout_builder_st
 * @group rest
 */
#[Group('layout_builder_st')]
#[Group('rest')]
#[RunTestsInSeparateProcesses]
class LayoutBuilderEntityViewDisplayJsonBasicAuthTest extends LayoutBuilderEntityViewDisplayResourceTestBase {

  use BasicAuthResourceTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['basic_auth'];

  /**
   * {@inheritdoc}
   */
  protected static $format = 'json';

  /**
   * {@inheritdoc}
   */
  protected static $mimeType = 'application/json';

  /**
   * {@inheritdoc}
   */
  protected static $auth = 'basic_auth';

}
