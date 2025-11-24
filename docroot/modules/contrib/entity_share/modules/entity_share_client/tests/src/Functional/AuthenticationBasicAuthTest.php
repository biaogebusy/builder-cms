<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

/**
 * Functional test class for import with "Basic Auth" authorization.
 *
 * @group entity_share
 * @group entity_share_client
 */
class AuthenticationBasicAuthTest extends AuthenticationBasicAuthTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->postSetupFixture();
  }

}
