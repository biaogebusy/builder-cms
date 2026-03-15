<?php

namespace Drupal\Tests\social_auth\Functional;

/**
 * Test Social Auth Login block.
 *
 * @group social_auth
 */
class SocialAuthLoginBlockTest extends SocialAuthTestBase {

  /**
   * Test that the block is showing up.
   *
   * No need to specify a provider since it is by default NULL and Social Auth
   * does not have any authentication route by itself.
   */
  public function testBlockExists() {
    parent::checkLinkToProviderExists();
  }

}
