<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

/**
 * Functional test class for import with "Basic Auth" authorization.
 *
 * When Rename Admin Paths is used.
 *
 * @group entity_share
 * @group entity_share_client
 */
class AuthenticationBasicAuthRenameAdminPathsTest extends AuthenticationBasicAuthTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'rename_admin_paths',
  ];

  /**
   * {@inheritdoc}
   */
  protected $loginPath = 'identification/login';

  /**
   * The Drupal config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->configFactory = $this->container->get('config.factory');
    $config = $this->configFactory->getEditable('rename_admin_paths.settings');
    $config->set('user_path', 1);
    $config->set('user_path_value', 'identification');
    $config->save();

    // Only rebuild router, do not flush all caches.
    $this->container->get('router.builder')->rebuild();

    $this->postSetupFixture();
  }

}
